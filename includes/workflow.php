<?php
declare(strict_types=1);
function v2_ready(): bool { static $ready; return $ready ??= (bool)q_val("SELECT 1 FROM schema_version WHERE version='2.0-core'"); }
function v2_require(): void {
    if (!v2_ready()) { abort(503,'Upgrade needed','Run scripts/upgrade_v2.php from the command line before using V2 Core. Your existing data has not been changed.'); }
}
function require_actor(int $userId, string $permission): void {
    $u=current_user(); if (!$u || (int)$u['user_id']!==$userId || !can($permission)) { throw new DomainException('You do not have permission for this action.'); }
}
function clinical_owner(array $v, int $userId, bool $editable = true): void {
    require_actor($userId,'exam.write');
    if (!can_write_exam($v)) { throw new DomainException('Only the responsible doctor can change clinical records.'); }
    if ($editable && ($v['status']!=='OPEN' || ($v['clinical_status']??'IN_PROGRESS')==='COMPLETED')) { throw new DomainException('Clinical work is complete. Saved records are read-only; finalized examinations use the correction workflow.'); }
}
function queue_rows(bool $history=false): array {
    return q_all("SELECT a.*, p.full_name, p.patient_code, d.full_name AS expected_doctor, v.visit_no,
        TIMESTAMPDIFF(MINUTE,a.arrived_at,NOW()) AS waiting_minutes
        FROM arrival_queue_entry a JOIN patient p ON p.patient_id=a.patient_id
        LEFT JOIN doctor d ON d.doctor_id=a.expected_doctor_id LEFT JOIN clinical_visit v ON v.visit_id=a.visit_id
        WHERE ".($history ? "a.arrived_at>=CURDATE()" : "a.status='WAITING'")."
        ORDER BY (a.status='WAITING') DESC, (a.origin='EMERGENCY') DESC,a.arrived_at,a.arrival_id");
}
function arrival_checkin(int $patientId, ?int $appointmentId, string $origin, ?int $expectedDoctor, string $note, bool $otherDate, int $userId): int {
    require_actor($userId,'appointment.write'); v2_require();
    if (mb_strlen($note)>2000) { throw new DomainException('Reception note must be at most 2000 characters.'); }
    return tx(function()use($patientId,$appointmentId,$origin,$expectedDoctor,$note,$otherDate,$userId){
        $p=q_one('SELECT * FROM patient WHERE patient_id=? FOR UPDATE',[$patientId]);
        if (!$p || $p['archived_at']!==null) { throw new DomainException('Choose an active patient.'); }
        if(q_val("SELECT 1 FROM arrival_queue_entry WHERE patient_id=? AND status='WAITING'",[$patientId])) { throw new DomainException('This patient is already waiting. Open their queue entry.'); }
        if(q_val("SELECT 1 FROM clinical_visit WHERE patient_id=? AND status='OPEN'",[$patientId])) { throw new DomainException('This patient already has an open visit. Finish it before checking in again.'); }
        if($appointmentId) {
            $a=q_one('SELECT * FROM appointment WHERE appointment_id=? FOR UPDATE',[$appointmentId]);
            if(!$a || (int)$a['patient_id']!==$patientId || !in_array($a['status'],['SCHEDULED','CONFIRMED'],true)) { throw new DomainException('Choose a scheduled or confirmed appointment for this patient.'); }
            if(q_val('SELECT 1 FROM clinical_visit WHERE appointment_id=?',[$appointmentId])) { throw new DomainException('This appointment already has a visit and cannot be checked in again.'); }
            if(substr($a['start_at'],0,10)!==date('Y-m-d') && !$otherDate) { throw new DomainException('This booking is for another day. Confirm early/late arrival to continue.'); }
            $origin='APPOINTMENT'; $expectedDoctor=(int)$a['doctor_id'];
        } elseif(!in_array($origin,['WALK_IN','EMERGENCY'],true)) { throw new DomainException('Choose walk-in or emergency.'); }
        if($expectedDoctor && !q_val("SELECT 1 FROM doctor WHERE doctor_id=? AND profile_status='ACTIVE'",[$expectedDoctor])) { throw new DomainException('Choose an active doctor.'); }
        q('INSERT INTO arrival_queue_entry(patient_id,appointment_id,expected_doctor_id,origin,reception_note,recorded_by) VALUES (?,?,?,?,?,?)',[$patientId,$appointmentId,$expectedDoctor,$origin,trim($note)?:null,$userId]);
        $id=(int)db()->lastInsertId();audit('ARRIVAL_RECORDED','arrival',$id);return $id;
    });
}
function arrival_claim(int $arrivalId,string $mode,int $userId): int {
    require_actor($userId,'exam.write');v2_require();$u=current_user();
    if(!$u['doctor_id']) { throw new DomainException('An active doctor profile is required to start a visit.'); }
    if(!in_array($mode,['QUICK','STANDARD'],true)) { throw new DomainException('Choose Quick or Standard.'); }
    return tx(function()use($arrivalId,$mode,$userId,$u){
        $a=q_one('SELECT * FROM arrival_queue_entry WHERE arrival_id=? FOR UPDATE',[$arrivalId]);
        if(!$a || $a['status']!=='WAITING') { throw new DomainException('This entry is no longer waiting. Refresh the queue.'); }
        if($a['expected_doctor_id'] && (int)$a['expected_doctor_id']!==(int)$u['doctor_id']) { throw new DomainException('This patient is assigned to another doctor. Reception must change the assignment first.'); }
        $p=q_one('SELECT archived_at FROM patient WHERE patient_id=? FOR UPDATE',[$a['patient_id']]);
        if(!$p || $p['archived_at']!==null) { throw new DomainException('The patient is no longer active.'); }
        if(q_val("SELECT 1 FROM clinical_visit WHERE patient_id=? AND status='OPEN'",[$a['patient_id']])) { throw new DomainException('This patient already has an open visit.'); }
        if($a['appointment_id']) {
            $ap=q_one('SELECT * FROM appointment WHERE appointment_id=? FOR UPDATE',[$a['appointment_id']]);
            if(!$ap || !in_array($ap['status'],['SCHEDULED','CONFIRMED'],true) || (int)$ap['doctor_id']!==(int)$u['doctor_id'] || q_val('SELECT 1 FROM clinical_visit WHERE appointment_id=?',[$a['appointment_id']])) { throw new DomainException('The booking changed or already has a visit. Ask reception to review it.'); }
        }
        q('INSERT INTO clinical_visit(visit_no,patient_id,doctor_id,appointment_id,visit_type,note,opened_by,workflow_mode) VALUES (?,?,?,?,?,?,?,?)',
            ['TMP'.bin2hex(random_bytes(8)),$a['patient_id'],$u['doctor_id'],$a['appointment_id'],$a['origin'],$a['reception_note'],$userId,$mode]);
        $id=(int)db()->lastInsertId();q('UPDATE clinical_visit SET visit_no=? WHERE visit_id=?',[next_code('V',$id),$id]);
        q("UPDATE arrival_queue_entry SET status='CLAIMED',active_flag=NULL,claimed_by=?,claimed_at=NOW(),visit_id=? WHERE arrival_id=?",[$userId,$id,$arrivalId]);
        audit('ARRIVAL_CLAIMED','arrival',$arrivalId,'SUCCESS',['visit'=>$id,'mode'=>$mode]);return $id;
    });
}
function arrival_cancel(int $id,string $reason,int $userId): void {
    require_actor($userId,'appointment.write');
    if(trim($reason)==='' || mb_strlen($reason)>500) { throw new DomainException('Enter a cancellation reason (up to 500 characters).'); }
    tx(function()use($id,$reason,$userId){
        $a=q_one('SELECT * FROM arrival_queue_entry WHERE arrival_id=? FOR UPDATE',[$id]);
        if(!$a || $a['status']!=='WAITING') { throw new DomainException('Only a waiting entry can be cancelled.'); }
        q("UPDATE arrival_queue_entry SET status='CANCELLED',active_flag=NULL,cancelled_by=?,cancelled_at=NOW(),cancel_reason=? WHERE arrival_id=?",[$userId,trim($reason),$id]);audit('ARRIVAL_CANCELLED','arrival',$id);
    });
}
function visit_note_latest(int $id): ?array { return q_one('SELECT r.*,u.display_name FROM visit_note_revision r JOIN user_account u ON u.user_id=r.authored_by WHERE visit_id=? ORDER BY revision_id DESC LIMIT 1',[$id]); }
function workflow_action(int $id,string $action,array $input,int $userId): void {
    v2_require();
    tx(function()use($id,$action,$input,$userId){
        $v=q_one('SELECT * FROM clinical_visit WHERE visit_id=? FOR UPDATE',[$id]);
        if(!$v || $v['status']!=='OPEN') { throw new DomainException('Only open visits can be updated.'); }
        if($action==='admin_complete') {
            require_actor($userId,'appointment.write');
            if($v['administrative_status']==='COMPLETED')return;
            if(empty($input['confirm_review'])) { throw new DomainException('Confirm that the reception details and follow-up arrangements have been reviewed.'); }
            q("UPDATE clinical_visit SET administrative_status='COMPLETED',administrative_completed_by=?,administrative_completed_at=NOW() WHERE visit_id=?",[$userId,$id]);
        } else {
            clinical_owner($v,$userId);
            switch($action) {
            case 'mode':
                $mode=patient_scalar($input,'mode');
                if(!in_array($mode,['QUICK','STANDARD'],true) || $v['workflow_mode']==='STANDARD' || ($v['workflow_mode']==='QUICK' && $mode!=='STANDARD')) { throw new DomainException('Only legacy setup or Quick → Standard is allowed.'); }
                q('UPDATE clinical_visit SET workflow_mode=? WHERE visit_id=?',[$mode,$id]); break;
            case 'note':
                $note=patient_scalar($input,'clinical_note');
                if($note==='' || mb_strlen($note)>10000) { throw new DomainException('Enter a clinical note (up to 10,000 characters).'); }
                q('INSERT INTO visit_note_revision(visit_id,note,authored_by) VALUES (?,?,?)',[$id,$note,$userId]);break;
            case 'clinical_complete':
                if($v['workflow_mode']==='LEGACY') { throw new DomainException('Choose Quick or Standard before completing this visit.'); }
                if(q_val("SELECT 1 FROM clinical_examination WHERE visit_id=? AND status='DRAFT'",[$id])) { throw new DomainException('Finalize or discard the draft examination first.'); }
                if($v['workflow_mode']==='QUICK' && !visit_note_latest($id)) { throw new DomainException('Save a clinical note before completing a Quick visit.'); }
                if($v['workflow_mode']==='STANDARD' && !q_val("SELECT 1 FROM clinical_examination WHERE visit_id=? AND status='FINALIZED'",[$id])) { throw new DomainException('A Standard visit needs a finalized examination.'); }
                q("UPDATE clinical_visit SET clinical_status='COMPLETED',clinical_completed_by=?,clinical_completed_at=NOW() WHERE visit_id=?",[$userId,$id]);break;
            default: throw new DomainException('Unknown workflow action.');
            }
        }
        audit('VISIT_WORKFLOW_UPDATED','visit',$id,'SUCCESS',['mode'=>$action]);
    });
}
function fdi_teeth(): array { $t=[];foreach([1,2,3,4,5,6,7,8] as $q){foreach(range(1,$q<=4?8:5) as $n)$t[]=(string)($q*10+$n);}return $t; }
function clinical_item_add(int $visitId,array $input,int $userId): void {
    $type=patient_scalar($input,'item_type');$description=patient_scalar($input,'description');
    $teeth=array_values(array_unique(array_filter(preg_split('/[\s,]+/',patient_scalar($input,'teeth')))));sort($teeth,SORT_NUMERIC);
    $surfaces=array_values(array_unique(array_filter(preg_split('/[\s,]+/',strtoupper(patient_scalar($input,'surfaces'))))));
    if(!in_array($type,['FINDING','RECOMMENDATION','PROCEDURE'],true) || $description==='' || mb_strlen($description)>2000) { throw new DomainException('Choose a record type and enter a description (up to 2000 characters).'); }
    foreach($teeth as $t){if(!in_array((string)$t,fdi_teeth(),true))throw new DomainException('Use valid FDI permanent or primary tooth numbers.');}
    foreach($surfaces as $s){if(!in_array($s,['M','D','O','I','B','L','P'],true))throw new DomainException('Surface codes: M, D, O, I, B, L or P.');}
    if($surfaces && !$teeth)throw new DomainException('Choose a tooth when recording a surface.');
    tx(function()use($visitId,$input,$userId,$type,$description,$teeth,$surfaces){
        $v=q_one('SELECT * FROM clinical_visit WHERE visit_id=? FOR UPDATE',[$visitId]);if(!$v)throw new DomainException('Visit not found.');clinical_owner($v,$userId);
        q('INSERT INTO clinical_item(visit_id,item_type,teeth,surfaces,description,recorded_by) VALUES (?,?,?,?,?,?)',[$visitId,$type,implode(',',$teeth)?:null,implode(',',$surfaces)?:null,$description,$userId]);
        audit('CLINICAL_ITEM_RECORDED','clinical_item',(int)db()->lastInsertId(),'SUCCESS',['mode'=>$type]);
    });
}
function clinical_item_void(int $id,string $reason,int $userId): void {
    if(trim($reason)==='' || mb_strlen($reason)>500)throw new DomainException('Enter a reason (up to 500 characters).');
    tx(function()use($id,$reason,$userId){
        $item=q_one('SELECT * FROM clinical_item WHERE item_id=? FOR UPDATE',[$id]);if(!$item || $item['voided_at'])throw new DomainException('Record not found or already voided.');
        $v=q_one('SELECT * FROM clinical_visit WHERE visit_id=? FOR UPDATE',[$item['visit_id']]);clinical_owner($v,$userId);
        q('UPDATE clinical_item SET voided_by=?,voided_at=NOW(),void_reason=? WHERE item_id=?',[$userId,trim($reason),$id]);audit('CLINICAL_ITEM_VOIDED','clinical_item',$id);
    });
}
function workflow_setting(string $key,int $default): int {
    return v2_ready() ? (int)(q_val('SELECT setting_value FROM clinic_setting WHERE setting_key=?',[$key]) ?? cfg($key,$default)) : (int)cfg($key,$default);
}
