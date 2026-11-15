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
    return tx(function () use ($apptId, $userId, $confirmOtherDate) {
        $a = q_one('SELECT * FROM appointment WHERE appointment_id = ? FOR UPDATE', [$apptId]);
        if (!$a) { throw new DomainException('Appointment not found.'); }
        $ex = q_one('SELECT visit_id, status FROM clinical_visit WHERE appointment_id = ?', [$apptId]);
        if ($ex) {
            if ($ex['status'] === 'CANCELLED') { throw new DomainException('The visit opened from this appointment was cancelled and the link cannot be reused. Cancel this appointment and book a new one.'); }
            return [(int) $ex['visit_id'], false];
        }
        if (!in_array($a['status'], ['SCHEDULED', 'CONFIRMED'], true)) { throw new DomainException('A visit can be opened only from a scheduled or confirmed appointment.'); }
        $pat = q_one('SELECT archived_at FROM patient WHERE patient_id = ?', [$a['patient_id']]);
        if ($pat['archived_at'] !== null) { throw new DomainException('This patient is archived and read-only.'); }
        if (substr($a['start_at'], 0, 10) !== date('Y-m-d') && !$confirmOtherDate) { throw new OverlapWarning('This appointment is not today. Tick the confirmation to open the visit anyway.'); }
        q("INSERT INTO clinical_visit (visit_no, patient_id, doctor_id, appointment_id, visit_type, opened_by) VALUES (?,?,?,?,'APPOINTMENT',?)",
          ['TMP' . bin2hex(random_bytes(8)), $a['patient_id'], $a['doctor_id'], $apptId, $userId]);
        $id = (int) db()->lastInsertId();
        q('UPDATE clinical_visit SET visit_no = ? WHERE visit_id = ?', [next_code('V', $id), $id]);
        audit('VISIT_OPENED', 'visit', $id, 'SUCCESS', ['mode' => 'appointment']);
        return [$id, true];
    });
}
function visit_open_walkin(int $patientId, int $doctorId, string $type, ?string $note, int $userId): int {
    return tx(function () use ($patientId, $doctorId, $type, $note, $userId) {
        if (!in_array($type, ['WALK_IN', 'EMERGENCY'], true)) { throw new DomainException('Choose a visit type.'); }
        $pat = q_one('SELECT archived_at FROM patient WHERE patient_id = ? FOR UPDATE', [$patientId]);
        if (!$pat) { throw new DomainException('Patient not found.'); }
        if ($pat['archived_at'] !== null) { throw new DomainException('This patient is archived and read-only.'); }
        $doc = q_one('SELECT profile_status FROM doctor WHERE doctor_id = ?', [$doctorId]);
        if (!$doc || $doc['profile_status'] !== 'ACTIVE') { throw new DomainException('Choose an active doctor.'); }
        q('INSERT INTO clinical_visit (visit_no, patient_id, doctor_id, visit_type, note, opened_by) VALUES (?,?,?,?,?,?)',
          ['TMP' . bin2hex(random_bytes(8)), $patientId, $doctorId, $type, $note, $userId]);
        $id = (int) db()->lastInsertId();
        q('UPDATE clinical_visit SET visit_no = ? WHERE visit_id = ?', [next_code('V', $id), $id]);
        audit('VISIT_OPENED', 'visit', $id, 'SUCCESS', ['mode' => strtolower($type)]);
        return $id;
    });
}
function visit_close(int $id, int $userId): void {
    tx(function () use ($id, $userId) {
        $v = q_one('SELECT * FROM clinical_visit WHERE visit_id = ? FOR UPDATE', [$id]);
        if (!$v) { throw new DomainException('Visit not found.'); }
        if ($v['status'] !== 'OPEN') { throw new DomainException('Only an open visit can be closed.'); }
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
        if ($v['status'] !== 'OPEN') { throw new DomainException('Only an open visit can be cancelled.'); }
        if (q_val("SELECT 1 FROM clinical_examination WHERE visit_id = ? AND status <> 'DISCARDED' LIMIT 1", [$id])) { throw new DomainException('A visit that has an examination cannot be cancelled. Finalize and close it instead.'); }
        q("UPDATE clinical_visit SET status = 'CANCELLED', cancelled_by = ?, cancelled_at = NOW(), cancel_reason_code = ? WHERE visit_id = ?", [$userId, $reasonCode, $id]);
        audit('VISIT_CANCELLED', 'visit', $id, 'SUCCESS', ['status_to' => 'CANCELLED', 'reason_code' => $reasonCode]);
    });
}
