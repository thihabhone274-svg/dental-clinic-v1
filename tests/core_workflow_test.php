<?php
// Isolated *_test database only, after baseline SQL + scripts/upgrade_v2.php.
require __DIR__.'/../includes/bootstrap.php';
if(!str_ends_with((string)q_val('SELECT DATABASE()'),'_test'))exit("Use a *_test database.\n");
$n=0;
function verify(bool $v,string $m):void{global $n;if(!$v)throw new RuntimeException('FAIL: '.$m);$n++;echo "PASS $m\n";}
function rejects(callable $f,string $m):void{try{$f();}catch(DomainException $e){verify(true,$m);return;}throw new RuntimeException('FAIL: accepted '.$m);}
function as_user(int $id):void{$_SESSION['user_id']=$id;current_user(true);}
function make_user(string $name,string $role):int{
 q("INSERT INTO user_account(username,username_key,display_name,password_hash) VALUES (?,?,?,?)",[$name,$name,'Demo '.ucfirst($name),password_hash('Testing123!',PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();q('INSERT INTO user_role(user_id,role_code) VALUES (?,?)',[$id,$role]);if($role==='DOCTOR')doctor_ensure_profile($id,'Dr '.ucfirst($name));return $id;
}
$admin=make_user('admin','ADMIN');$staff=make_user('reception','STAFF');$doctor=make_user('doctor','DOCTOR');$other=make_user('otherdoctor','DOCTOR');
$doc=(int)q_val('SELECT doctor_id FROM doctor WHERE user_id=?',[$doctor]);$doc2=(int)q_val('SELECT doctor_id FROM doctor WHERE user_id=?',[$other]);
as_user($staff);$e=[];$pd=patient_validate(['full_name'=>'Mya Demo','registration_date'=>date('Y-m-d'),'gender'=>'Female','nrc_mode'=>'structured','nrc_region'=>'14','nrc_township'=>'PATANA','nrc_type'=>'N','nrc_serial'=>'001234'],$e,true);verify(!$e,'Myanmar registration validation');$pid=patient_create($pd,$staff,false);
$aid=arrival_checkin($pid,null,'WALK_IN',$doc,'First visit',false,$staff);verify((int)q_val("SELECT COUNT(*) FROM arrival_queue_entry WHERE status='WAITING'")===1,'Reception check-in');
rejects(fn()=>arrival_checkin($pid,null,'WALK_IN',null,'',false,$staff),'Duplicate waiting entry blocked');
rejects(fn()=>arrival_claim($aid,'QUICK',$staff),'Reception cannot claim');
as_user($admin);rejects(fn()=>arrival_claim($aid,'QUICK',$admin),'Admin without doctor profile cannot claim');
as_user($other);rejects(fn()=>arrival_claim($aid,'QUICK',$other),'Other doctor cannot claim assigned patient');
as_user($doctor);$vid=arrival_claim($aid,'QUICK',$doctor);$v=visit_find($vid);verify($v['workflow_mode']==='QUICK' && (int)$v['doctor_id']===$doc,'Doctor starts Quick visit');
rejects(fn()=>arrival_claim($aid,'QUICK',$doctor),'Claim cannot be repeated');
rejects(fn()=>workflow_action($vid,'clinical_complete',[],$doctor),'Quick completion needs note');
rejects(fn()=>visit_close($vid,$doctor),'Premature closure blocked');
as_user($staff);rejects(fn()=>arrival_checkin($pid,null,'WALK_IN',null,'',false,$staff),'Open visit blocks second arrival');rejects(fn()=>workflow_action($vid,'note',['clinical_note'=>'Unauthorized'],$staff),'Reception cannot write clinical note');
as_user($admin);rejects(fn()=>workflow_action($vid,'note',['clinical_note'=>'Unauthorized'],$admin),'Admin cannot write clinical note');
as_user($doctor);workflow_action($vid,'note',['clinical_note'=>'Focused complaint and assessment.'],$doctor);workflow_action($vid,'note',['clinical_note'=>'Revised assessment; records retained.'],$doctor);verify((int)q_val('SELECT COUNT(*) FROM visit_note_revision WHERE visit_id=?',[$vid])===2,'Notes retain revision history');
clinical_item_add($vid,['item_type'=>'FINDING','teeth'=>'11,12','surfaces'=>'M','description'=>'Demo finding'],$doctor);
clinical_item_add($vid,['item_type'=>'RECOMMENDATION','teeth'=>'11','description'=>'Demo recommendation'],$doctor);
clinical_item_add($vid,['item_type'=>'PROCEDURE','teeth'=>'11','description'=>'Demo delivered treatment'],$doctor);
verify((int)q_val('SELECT COUNT(DISTINCT item_type) FROM clinical_item WHERE visit_id=?',[$vid])===3,'Findings, proposed care and performed care separate');
rejects(fn()=>clinical_item_add($vid,['item_type'=>'FINDING','teeth'=>'99','description'=>'Invalid tooth'],$doctor),'Invalid FDI rejected');
rejects(fn()=>clinical_item_add($vid,['item_type'=>'FINDING','surfaces'=>'M','description'=>'No tooth'],$doctor),'Surface requires tooth');
$item=(int)q_val('SELECT MIN(item_id) FROM clinical_item WHERE visit_id=?',[$vid]);clinical_item_void($item,'Wrong tooth',$doctor);verify(q_val('SELECT voided_at FROM clinical_item WHERE item_id=?',[$item])!==null,'Voiding retains record');
workflow_action($vid,'clinical_complete',[],$doctor);rejects(fn()=>workflow_action($vid,'note',['clinical_note'=>'Changed'],$doctor),'Completed notes immutable');rejects(fn()=>clinical_item_add($vid,['item_type'=>'PROCEDURE','description'=>'Late edit'],$doctor),'Completed procedures immutable');
as_user($staff);rejects(fn()=>workflow_action($vid,'admin_complete',[],$staff),'Reception review requires confirmation');workflow_action($vid,'admin_complete',['confirm_review'=>1],$staff);visit_close($vid,$staff);verify(visit_find($vid)['status']==='CLOSED','Both completions allow closure');
$aid=arrival_checkin($pid,null,'WALK_IN',$doc,'Second encounter',false,$staff);as_user($doctor);$vid2=arrival_claim($aid,'QUICK',$doctor);workflow_action($vid2,'note',['clinical_note'=>'Preserve when upgraded.'],$doctor);workflow_action($vid2,'mode',['mode'=>'STANDARD'],$doctor);verify(visit_note_latest($vid2)['note']==='Preserve when upgraded.','Upgrade retains note');
rejects(fn()=>workflow_action($vid2,'mode',['mode'=>'QUICK'],$doctor),'Standard cannot downgrade');rejects(fn()=>workflow_action($vid2,'clinical_complete',[],$doctor),'Standard needs finalized exam');
$e=[];[$c,$x]=exam_validate(['examined_at'=>date('Y-m-d H:i:s'),'general_note'=>'Demo full examination'],$e);verify(!$e,'Exam validates');$eid=exam_save_draft($vid2,null,$c,$x,$doctor);
as_user($admin);rejects(fn()=>exam_save_draft($vid2,$eid,$c,$x,$admin),'Service blocks admin exam edits');
as_user($doctor);exam_save_draft($vid2,$eid,$c,$x,$doctor);verify((int)exam_find($eid)['authored_by']===$doctor,'Original exam author retained');rejects(fn()=>workflow_action($vid2,'clinical_complete',[],$doctor),'Draft exam blocks completion');exam_finalize($eid,$doctor);workflow_action($vid2,'clinical_complete',[],$doctor);
as_user($staff);workflow_action($vid2,'admin_complete',['confirm_review'=>1],$staff);visit_close($vid2,$staff);verify(visit_find($vid2)['status']==='CLOSED','Standard end-to-end completion');
$future=date('Y-m-d',strtotime('+1 day'));$e=[];$d=appt_validate(['doctor_id'=>$doc,'appt_date'=>$future,'start_time'=>'09:00','reason'=>'Check-up'],$e,true);$d['patient_id']=$pid;
$id1=appt_create($d,$staff,false);verify(appt_find($id1)['end_at']==="$future 09:30:00",'Nominal end from gap');
$d2=$d;$d2['start_at']="$future 09:29:00";rejects(fn()=>appt_create($d2,$staff,true),'29 minute doctor gap blocked even with override');$d2['start_at']="$future 09:30:00";$id2=appt_create($d2,$staff,false);verify($id2>0,'30 minute boundary allowed');
$d3=$d;$d3['doctor_id']=$doc2;rejects(fn()=>appt_create($d3,$staff,false),'Patient overlap warns');$id3=appt_create($d3,$staff,true);verify($id3>0,'Patient override allowed');
$d4=$d;$d4['doctor_id']=$doc2;$d4['start_at']="$future 11:00:00";$d4['reason']='FORGED';appt_reschedule($id2,$d4,'Patient request',$staff,false);$a=appt_find($id2);verify((int)$a['doctor_id']===$doc && $a['reason']==='Check-up','Reschedule changes time only');
appt_reassign($id2,$doc2,'Doctor schedule changed',false,$staff);verify((int)appt_find($id2)['doctor_id']===$doc2,'Separate reassignment');verify((int)q_val("SELECT old_doctor_id FROM appointment_change WHERE appointment_id=? AND change_type='REASSIGNED'",[$id2])===$doc,'Old doctor retained');
rejects(fn()=>arrival_checkin($pid,$id2,'APPOINTMENT',null,'',false,$staff),'Off-day arrival requires confirmation');$arr=arrival_checkin($pid,$id2,'APPOINTMENT',null,'',true,$staff);rejects(fn()=>appt_set_status($id2,'NO_SHOW',$staff),'Waiting patient cannot be no-show');rejects(fn()=>appt_reschedule($id2,$d4,'Move',$staff,false),'Checked-in reschedule blocked');
appt_reassign($id2,$doc,'Back to doctor',false,$staff);verify((int)q_val('SELECT expected_doctor_id FROM arrival_queue_entry WHERE arrival_id=?',[$arr])===$doc,'Reassignment updates waiting queue');as_user($doctor);$linked=arrival_claim($arr,'QUICK',$doctor);as_user($staff);rejects(fn()=>appt_reassign($id2,$doc2,'No longer allowed',false,$staff),'Any linked visit permanently blocks reassign');
as_user($doctor);workflow_action($linked,'note',['clinical_note'=>'Review complete.'],$doctor);workflow_action($linked,'clinical_complete',[],$doctor);as_user($staff);workflow_action($linked,'admin_complete',['confirm_review'=>1],$staff);visit_close($linked,$staff);verify(appt_find($id2)['status']==='COMPLETED','Closure completes appointment');
// Leave demo data for browser review.
$e=[];$new=patient_validate(['full_name'=>'Ko Aung Demo','registration_date'=>date('Y-m-d')],$e,true);$pid2=patient_create($new,$staff,false);$arrival=arrival_checkin($pid2,null,'WALK_IN',null,'Patient requests a routine check-up.',false,$staff);
verify(count(queue_rows())===1,'Queue lists remaining arrivals');
echo "RESULT: $n core workflow checks passed.\n";
