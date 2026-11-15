<?php
declare(strict_types=1);
function appt_find(int $id): ?array {
    return q_one('SELECT a.*, p.full_name AS patient_name, p.patient_code, p.archived_at AS patient_archived, d.full_name AS doctor_name, d.user_id AS doctor_user_id,
                  v.visit_id, v.status AS visit_status FROM appointment a JOIN patient p ON p.patient_id = a.patient_id JOIN doctor d ON d.doctor_id = a.doctor_id
                  LEFT JOIN clinical_visit v ON v.appointment_id = a.appointment_id WHERE a.appointment_id = ?', [$id]);
}
function appt_list(array $f): array {
    $w = ['1=1']; $p = [];
    if (!empty($f['from'])) { $w[] = 'a.start_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
    if (!empty($f['to'])) { $w[] = 'a.start_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
    if (!empty($f['doctor_id'])) { $w[] = 'a.doctor_id = ?'; $p[] = (int) $f['doctor_id']; }
    if (!empty($f['patient_id'])) { $w[] = 'a.patient_id = ?'; $p[] = (int) $f['patient_id']; }
    if (!empty($f['status'])) { $w[] = 'a.status = ?'; $p[] = $f['status']; }
    $u = current_user();
    if ($u && !has_role('ADMIN') && !has_role('STAFF') && has_role('DOCTOR')) { $w[] = 'a.doctor_id = ?'; $p[] = (int) $u['doctor_id']; }   // doctors see their own bookings only
    return q_all('SELECT a.*, p.full_name AS patient_name, p.patient_code, d.full_name AS doctor_name, v.visit_id, v.status AS visit_status
                  FROM appointment a JOIN patient p ON p.patient_id = a.patient_id JOIN doctor d ON d.doctor_id = a.doctor_id
                  LEFT JOIN clinical_visit v ON v.appointment_id = a.appointment_id WHERE ' . implode(' AND ', $w) . ' ORDER BY a.start_at ASC LIMIT 500', $p);
}
function appt_has_blocking_visit(int $id): bool { return (bool) q_val("SELECT 1 FROM clinical_visit WHERE appointment_id = ? AND status IN ('OPEN','CLOSED')", [$id]); }
function appt_doctor_conflicts(int $doctorId, string $start, string $end, ?int $excludeId): array {
    return q_all("SELECT appointment_no, start_at, end_at FROM appointment WHERE doctor_id = ? AND status IN ('SCHEDULED','CONFIRMED') AND start_at < ? AND end_at > ? AND appointment_id <> ? ORDER BY start_at",
                 [$doctorId, $end, $start, $excludeId ?? 0]);
}
function appt_patient_overlaps(int $patientId, string $start, string $end, ?int $excludeId): array {
    return q_all("SELECT appointment_no, start_at, end_at FROM appointment WHERE patient_id = ? AND status IN ('SCHEDULED','CONFIRMED') AND start_at < ? AND end_at > ? AND appointment_id <> ?",
                 [$patientId, $end, $start, $excludeId ?? 0]);
}
function appt_validate(array $in, array &$err, bool $needsPatient): array {
    $d = ['doctor_id' => (int) ($in['doctor_id'] ?? 0), 'reason' => v_text($in, 'reason', $err, 'Reason', ['max' => 255]),
          'note' => v_text($in, 'note', $err, 'Note', ['max' => 2000, 'multiline' => true])];
    if ($needsPatient) { $d['patient_id'] = (int) ($in['patient_id'] ?? 0); if ($d['patient_id'] <= 0) { $err['patient_id'] = 'Choose a patient.'; } }
    if ($d['doctor_id'] <= 0) { $err['doctor_id'] = 'Choose a doctor.'; }
    $date = v_date($in, 'appt_date', $err, 'Date', ['required' => true]);
    $times = [];
    foreach (['start_time' => 'Start time', 'end_time' => 'End time'] as $k => $lab) {
        $t = trim((string) ($in[$k] ?? ''));
        if ($t === '') { $err[$k] = "$lab is required."; } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) { $err[$k] = "$lab must be a valid time (HH:MM)."; } else { $times[$k] = $t; }
    }
    if ($date && count($times) === 2) {
        $d['start_at'] = "$date {$times['start_time']}:00"; $d['end_at'] = "$date {$times['end_time']}:00";
        if ($d['end_at'] <= $d['start_at']) { $err['end_time'] = 'End time must be after the start time.'; }
        elseif ($d['start_at'] < date('Y-m-d H:i:00')) { $err['appt_date'] = 'The appointment cannot start in the past.'; }
    }
    return $d;
}
/** Shared conflict check, run inside the transaction after the doctor row is locked (rechecked immediately before saving). */
function appt_check_conflicts(array $d, ?int $excludeId, bool $confirmOverlap): void {
    $doc = q_one("SELECT doctor_id, profile_status FROM doctor WHERE doctor_id = ? FOR UPDATE", [$d['doctor_id']]);
    if (!$doc || $doc['profile_status'] !== 'ACTIVE') { throw new DomainException('Choose an active doctor.'); }
    if ($c = appt_doctor_conflicts((int) $d['doctor_id'], $d['start_at'], $d['end_at'], $excludeId)) {
        throw new DomainException('This doctor already has ' . $c[0]['appointment_no'] . ' from ' . date('H:i', strtotime($c[0]['start_at'])) . ' to ' . date('H:i', strtotime($c[0]['end_at'])) . '. Choose another time. Nothing was saved.');
    }
    if (!$confirmOverlap && ($o = appt_patient_overlaps((int) $d['patient_id'], $d['start_at'], $d['end_at'], $excludeId))) {
        throw new OverlapWarning('This patient already has appointment ' . $o[0]['appointment_no'] . ' at that time. Tick the confirmation to book anyway.');
    }
}
function appt_create(array $d, int $userId, bool $confirmOverlap): int {
    return tx(function () use ($d, $userId, $confirmOverlap) {
        $pat = q_one('SELECT archived_at FROM patient WHERE patient_id = ? FOR UPDATE', [$d['patient_id']]);
        if (!$pat) { throw new DomainException('Patient not found.'); }
        if ($pat['archived_at'] !== null) { throw new DomainException('Archived patients cannot be given appointments.'); }
        appt_check_conflicts($d, null, $confirmOverlap);
        q('INSERT INTO appointment (appointment_no, patient_id, doctor_id, start_at, end_at, reason, note, created_by) VALUES (?,?,?,?,?,?,?,?)',
          ['TMP' . bin2hex(random_bytes(8)), $d['patient_id'], $d['doctor_id'], $d['start_at'], $d['end_at'], $d['reason'], $d['note'], $userId]);
        $id = (int) db()->lastInsertId();
        q('UPDATE appointment SET appointment_no = ? WHERE appointment_id = ?', [next_code('AP', $id), $id]);
        audit('APPT_CREATED', 'appointment', $id);
        return $id;
    });
}
function appt_reschedule(int $id, array $d, string $reason, int $userId, bool $confirmOverlap): void {
    tx(function () use ($id, $d, $reason, $userId, $confirmOverlap) {
        $a = q_one('SELECT * FROM appointment WHERE appointment_id = ? FOR UPDATE', [$id]);
        if (!$a) { throw new DomainException('Appointment not found.'); }
        if (!in_array($a['status'], ['SCHEDULED', 'CONFIRMED'], true)) { throw new DomainException('Only scheduled or confirmed appointments can be rescheduled.'); }
        if (appt_has_blocking_visit($id)) { throw new DomainException('A visit has already been opened for this appointment.'); }
        $d['patient_id'] = (int) $a['patient_id'];
        appt_check_conflicts($d, $id, $confirmOverlap);
        q('INSERT INTO appointment_change (appointment_id, change_type, old_start_at, old_end_at, new_start_at, new_end_at, reason, changed_by) VALUES (?,?,?,?,?,?,?,?)',
          [$id, 'RESCHEDULED', $a['start_at'], $a['end_at'], $d['start_at'], $d['end_at'], $reason, $userId]);
        q('UPDATE appointment SET doctor_id = ?, start_at = ?, end_at = ?, reason = ?, note = ?, updated_by = ? WHERE appointment_id = ?',
          [$d['doctor_id'], $d['start_at'], $d['end_at'], $d['reason'], $d['note'], $userId, $id]);
        audit('APPT_RESCHEDULED', 'appointment', $id);
    });
}
function appt_cancel(int $id, string $reason, int $userId): void {
    tx(function () use ($id, $reason, $userId) {
        $a = q_one('SELECT * FROM appointment WHERE appointment_id = ? FOR UPDATE', [$id]);
        if (!$a) { throw new DomainException('Appointment not found.'); }
        if (!in_array($a['status'], ['SCHEDULED', 'CONFIRMED'], true)) { throw new DomainException('Only scheduled or confirmed appointments can be cancelled.'); }
        if (appt_has_blocking_visit($id)) { throw new DomainException('A visit has already been opened for this appointment, so it cannot be cancelled.'); }
        q("UPDATE appointment SET status = 'CANCELLED', cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW(), updated_by = ? WHERE appointment_id = ?", [$reason, $userId, $userId, $id]);
        q('INSERT INTO appointment_change (appointment_id, change_type, old_start_at, old_end_at, reason, changed_by) VALUES (?,?,?,?,?,?)', [$id, 'CANCELLED', $a['start_at'], $a['end_at'], $reason, $userId]);
        audit('APPT_CANCELLED', 'appointment', $id, 'SUCCESS', ['status_from' => $a['status'], 'status_to' => 'CANCELLED']);
    });
}
function appt_set_status(int $id, string $to, int $userId): void {
    tx(function () use ($id, $to, $userId) {
        $a = q_one('SELECT * FROM appointment WHERE appointment_id = ? FOR UPDATE', [$id]);
        if (!$a) { throw new DomainException('Appointment not found.'); }
        $ok = ($to === 'CONFIRMED' && $a['status'] === 'SCHEDULED') || ($to === 'NO_SHOW' && in_array($a['status'], ['SCHEDULED', 'CONFIRMED'], true));
        if (!$ok) { throw new DomainException('That status change is not allowed from ' . label($a['status']) . '.'); }
        if (appt_has_blocking_visit($id)) { throw new DomainException('A visit has already been opened for this appointment.'); }
        if ($to === 'NO_SHOW' && $a['start_at'] > date('Y-m-d H:i:s')) { throw new DomainException('An appointment can be marked no-show only after its start time.'); }
        q('UPDATE appointment SET status = ?, updated_by = ? WHERE appointment_id = ?', [$to, $userId, $id]);
        audit('APPT_STATUS_CHANGED', 'appointment', $id, 'SUCCESS', ['status_from' => $a['status'], 'status_to' => $to]);
    });
}
function appt_changes(int $id): array {
    return q_all('SELECT c.*, u.display_name AS changed_by_name FROM appointment_change c JOIN user_account u ON u.user_id = c.changed_by WHERE c.appointment_id = ? ORDER BY c.change_id DESC', [$id]);
}
