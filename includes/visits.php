<?php
declare(strict_types=1);
function visit_find(int $id): ?array {
    return q_one('SELECT v.*, p.full_name AS patient_name, p.patient_code, p.archived_at AS patient_archived, d.full_name AS doctor_name, a.appointment_no
                  FROM clinical_visit v JOIN patient p ON p.patient_id = v.patient_id JOIN doctor d ON d.doctor_id = v.doctor_id
                  LEFT JOIN appointment a ON a.appointment_id = v.appointment_id WHERE v.visit_id = ?', [$id]);
}
function visit_list(array $f, int $limit, int $offset): array {
    $w = ['1=1']; $p = [];
    if (!empty($f['status'])) { $w[] = 'v.status = ?'; $p[] = $f['status']; }
    if (!empty($f['patient_id'])) { $w[] = 'v.patient_id = ?'; $p[] = (int) $f['patient_id']; }
    if (!empty($f['from'])) { $w[] = 'v.visited_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
    if (!empty($f['to'])) { $w[] = 'v.visited_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
    $u = current_user();
    if ($u && !has_role('ADMIN') && !has_role('STAFF') && has_role('DOCTOR') && cfg('doctor_scope') !== 'all') { $w[] = 'v.doctor_id = ?'; $p[] = (int) $u['doctor_id']; }
    $where = implode(' AND ', $w);
    $total = (int) q_val("SELECT COUNT(*) FROM clinical_visit v WHERE $where", $p);
    $rows = q_all("SELECT v.*, p.full_name AS patient_name, p.patient_code, d.full_name AS doctor_name,
                   (SELECT COUNT(*) FROM clinical_examination e WHERE e.visit_id = v.visit_id AND e.status = 'DRAFT') AS drafts
                   FROM clinical_visit v JOIN patient p ON p.patient_id = v.patient_id JOIN doctor d ON d.doctor_id = v.doctor_id
                   WHERE $where ORDER BY (v.status = 'OPEN') DESC, v.visited_at DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $p);
    return [$rows, $total];
}
function visit_open_from_appointment(int $apptId, int $userId, bool $confirmOtherDate): array {
    throw new DomainException('Check in at reception, then the responsible doctor starts the visit from the waiting queue.');
}
function visit_open_walkin(int $patientId, int $doctorId, string $type, ?string $note, int $userId): int {
    throw new DomainException('Check in at reception, then the responsible doctor starts the visit from the waiting queue.');
}
function visit_close(int $id, int $userId): void {
    tx(function () use ($id, $userId) {
        $v = q_one('SELECT * FROM clinical_visit WHERE visit_id = ? FOR UPDATE', [$id]);
        if (!$v) { throw new DomainException('Visit not found.'); }
        require_actor($userId, 'visit.close');
        if (!can_close_visit($v)) { throw new DomainException('This visit is outside your scope.'); }
        if ($v['status'] !== 'OPEN') { throw new DomainException('Only an open visit can be closed.'); }
        if (($v['workflow_mode'] ?? 'LEGACY') === 'LEGACY' || $v['clinical_status'] !== 'COMPLETED' || $v['administrative_status'] !== 'COMPLETED') { throw new DomainException('Complete both clinical work and the reception review before closing.'); }
        if (q_val("SELECT 1 FROM clinical_examination WHERE visit_id = ? AND status = 'DRAFT' LIMIT 1", [$id])) {
            throw new DomainException('This visit still has a draft examination. The doctor must finalize it, or discard it, before the visit can be closed.');
        }
        q("UPDATE clinical_visit SET status = 'CLOSED', closed_by = ?, closed_at = NOW() WHERE visit_id = ?", [$userId, $id]);
        audit('VISIT_CLOSED', 'visit', $id, 'SUCCESS', ['status_from' => 'OPEN', 'status_to' => 'CLOSED']);
        if ($v['appointment_id'] !== null) {
            $n = q("UPDATE appointment SET status = 'COMPLETED', updated_by = ? WHERE appointment_id = ? AND status IN ('SCHEDULED','CONFIRMED')", [$userId, $v['appointment_id']])->rowCount();
            if ($n) { audit('APPT_STATUS_CHANGED', 'appointment', (int) $v['appointment_id'], 'SUCCESS', ['status_to' => 'COMPLETED']); }
        }
    });
}
function visit_cancel(int $id, string $reasonCode, int $userId): void {
    tx(function () use ($id, $reasonCode, $userId) {
        if (!array_key_exists($reasonCode, clinic('visit_cancel_reasons'))) { throw new DomainException('Choose a reason.'); }
        $v = q_one('SELECT * FROM clinical_visit WHERE visit_id = ? FOR UPDATE', [$id]);
        if (!$v) { throw new DomainException('Visit not found.'); }
        require_actor($userId, 'visit.close');
        if (!can_write_exam($v)) { throw new DomainException('Only the responsible doctor can cancel an opened visit.'); }
        if ($v['status'] !== 'OPEN') { throw new DomainException('Only an open visit can be cancelled.'); }
        if (visit_note_latest($id) || q_val('SELECT 1 FROM clinical_item WHERE visit_id=?', [$id]) || $v['clinical_status']==='COMPLETED') { throw new DomainException('This visit has clinical work. Complete and close it instead.'); }
        if (q_val("SELECT 1 FROM clinical_examination WHERE visit_id = ? AND status <> 'DISCARDED' LIMIT 1", [$id])) { throw new DomainException('A visit that has an examination cannot be cancelled. Finalize and close it instead.'); }
        q("UPDATE clinical_visit SET status = 'CANCELLED', cancelled_by = ?, cancelled_at = NOW(), cancel_reason_code = ? WHERE visit_id = ?", [$userId, $reasonCode, $id]);
        audit('VISIT_CANCELLED', 'visit', $id, 'SUCCESS', ['status_to' => 'CANCELLED', 'reason_code' => $reasonCode]);
    });
}
