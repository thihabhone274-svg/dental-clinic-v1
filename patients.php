<?php
declare(strict_types=1);
class OverlapWarning extends DomainException {}
const PATIENT_FIELDS = ['full_name', 'date_of_birth', 'gender', 'occupation', 'nationality', 'phone', 'address', 'nrc_number'];

function patient_find(int $id): ?array { return q_one('SELECT * FROM patient WHERE patient_id = ?', [$id]); }

/** Validate a registration/edit form. $creating adds the registration date. */
function patient_validate(array $in, array &$err, bool $creating): array {
    $today = date('Y-m-d');
    $d = [
        'full_name'     => v_text($in, 'full_name', $err, 'Patient name', ['required' => true, 'max' => 160]),
        'date_of_birth' => v_date($in, 'date_of_birth', $err, 'Date of birth', ['max' => $today]),
        'gender'        => v_text($in, 'gender', $err, 'Gender', ['max' => 40]),
        'occupation'    => v_text($in, 'occupation', $err, 'Occupation', ['max' => 120]),
        'nationality'   => v_text($in, 'nationality', $err, 'Nationality', ['max' => 80]),
        'phone'         => v_phone($in, 'phone', $err, 'Telephone number'),
        'address'       => v_text($in, 'address', $err, 'Address', ['max' => 1000, 'multiline' => true]),
        'nrc_number'    => v_text($in, 'nrc_number', $err, 'NRC', ['max' => 80]),
    ];
    if ($creating) { $d['registration_date'] = v_date($in, 'registration_date', $err, 'Registration date', ['required' => true, 'max' => $today]); }
    return $d;
}
/** Possible duplicates for HUMAN review. Never blocks, never merges. */
function patient_duplicates(array $d, ?int $excludeId = null): array {
    $nameKey = normalize_name((string) $d['full_name']); $phoneKey = digits_only($d['phone'] ?? null); $nrc = $d['nrc_number'] ?? null;
    $rows = q_all('SELECT patient_id, patient_code, full_name, date_of_birth, phone, nrc_number, name_key, phone_key, archived_at FROM patient
                   WHERE (name_key = ? OR (phone_key IS NOT NULL AND phone_key = ?) OR (nrc_number IS NOT NULL AND nrc_number = ?)) AND patient_id <> ? LIMIT 25',
                  [$nameKey, $phoneKey ?? '', $nrc ?? '', $excludeId ?? 0]);
    $out = [];
    foreach ($rows as $r) {
        $why = [];
        if ($nrc !== null && $r['nrc_number'] === $nrc) { $why[] = 'same NRC'; }
        if ($phoneKey !== null && $r['phone_key'] === $phoneKey) { $why[] = 'same telephone number'; }
        if ($r['name_key'] === $nameKey && !empty($d['date_of_birth']) && $r['date_of_birth'] === $d['date_of_birth']) { $why[] = 'same name and date of birth'; }
        $strong = $why !== [];
        if ($r['name_key'] === $nameKey && !$strong) { $why[] = 'same name only'; }
        $r['reasons'] = $why; $r['strength'] = $strong ? 'strong' : 'weak';
        $out[] = $r;
    }
    usort($out, fn($a, $b) => strcmp($a['strength'], $b['strength']) * -1);
    return $out;
}
function patient_create(array $d, int $userId, bool $duplicateReviewed): int {
    return tx(function () use ($d, $userId, $duplicateReviewed) {
        q('INSERT INTO patient (patient_code, full_name, name_key, date_of_birth, gender, occupation, nationality, phone, phone_key, address, nrc_number, registration_date, created_by)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
          ['TMP' . bin2hex(random_bytes(8)), $d['full_name'], normalize_name($d['full_name']), $d['date_of_birth'], $d['gender'], $d['occupation'], $d['nationality'],
           $d['phone'], digits_only($d['phone']), $d['address'], $d['nrc_number'], $d['registration_date'], $userId]);
        $id = (int) db()->lastInsertId();
        q('UPDATE patient SET patient_code = ? WHERE patient_id = ?', [next_code('PAT', $id), $id]);
        audit('PATIENT_CREATED', 'patient', $id, 'SUCCESS', ['mode' => $duplicateReviewed ? 'duplicate_reviewed' : 'new']);
        return $id;
    });
}
function patient_update(int $id, array $d, int $userId): bool {
    return tx(function () use ($id, $d, $userId) {
        $cur = q_one('SELECT * FROM patient WHERE patient_id = ? FOR UPDATE', [$id]);
        if (!$cur) { throw new DomainException('Patient not found.'); }
        if ($cur['archived_at'] !== null) { throw new DomainException('This patient is archived and read-only.'); }
        $changed = [];
        foreach (PATIENT_FIELDS as $f) { if ((string) ($cur[$f] ?? '') !== (string) ($d[$f] ?? '')) { $changed[] = $f; } }
        if (!$changed) { return false; }
        q('UPDATE patient SET full_name=?, name_key=?, date_of_birth=?, gender=?, occupation=?, nationality=?, phone=?, phone_key=?, address=?, nrc_number=?, updated_by=? WHERE patient_id=?',
          [$d['full_name'], normalize_name($d['full_name']), $d['date_of_birth'], $d['gender'], $d['occupation'], $d['nationality'], $d['phone'], digits_only($d['phone']), $d['address'], $d['nrc_number'], $userId, $id]);
        audit('PATIENT_UPDATED', 'patient', $id, 'SUCCESS', ['fields' => $changed]);   // field NAMES only, never values
        return true;
    });
}
/** Open work that must be finished before a patient can be archived. */
function patient_archive_blockers(int $id): array {
    $b = [];
    foreach (q_all("SELECT appointment_no, start_at FROM appointment WHERE patient_id = ? AND status IN ('SCHEDULED','CONFIRMED') ORDER BY start_at", [$id]) as $r) { $b[] = 'Appointment ' . $r['appointment_no'] . ' (' . fmt_dt($r['start_at']) . ') is still scheduled: cancel it or mark it no-show.'; }
    foreach (q_all("SELECT visit_no FROM clinical_visit WHERE patient_id = ? AND status = 'OPEN'", [$id]) as $r) { $b[] = 'Visit ' . $r['visit_no'] . ' is still open: close or cancel it.'; }
    return $b;
}
function patient_archive(int $id, string $reason, int $userId): void {
    tx(function () use ($id, $reason, $userId) {
        $cur = q_one('SELECT archived_at FROM patient WHERE patient_id = ? FOR UPDATE', [$id]);
        if (!$cur) { throw new DomainException('Patient not found.'); }
        if ($cur['archived_at'] !== null) { throw new DomainException('This patient is already archived.'); }
        if ($b = patient_archive_blockers($id)) { throw new DomainException(implode(' ', $b)); }
        q('UPDATE patient SET archived_at = NOW(), archived_by = ?, archive_reason = ?, updated_by = ? WHERE patient_id = ?', [$userId, $reason, $userId, $id]);
        audit('PATIENT_ARCHIVED', 'patient', $id);
    });
}
/** @return array{0:array,1:int} rows and total */
function patient_search(array $f, int $limit, int $offset): array {
    $w = ['1=1']; $p = [];
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . like_escape($q) . '%';
        $w[] = '(p.patient_code LIKE ? OR p.full_name LIKE ? OR p.phone LIKE ? OR p.nrc_number LIKE ? OR p.date_of_birth = ?)';
        array_push($p, $like, $like, $like, $like, preg_match('/^\d{4}-\d{2}-\d{2}$/', $q) ? $q : '0000-00-00');
    }
    $status = $f['status'] ?? 'active';
    if ($status === 'active') { $w[] = 'p.archived_at IS NULL'; } elseif ($status === 'archived') { $w[] = 'p.archived_at IS NOT NULL'; }
    $u = current_user();
    if ($u && !has_role('ADMIN') && !has_role('STAFF') && has_role('DOCTOR') && cfg('doctor_scope') !== 'all') {
        $w[] = "p.patient_id IN (SELECT patient_id FROM appointment WHERE doctor_id = ? AND status IN ('SCHEDULED','CONFIRMED') AND DATE(start_at) >= CURDATE()
                UNION SELECT patient_id FROM clinical_visit WHERE doctor_id = ? AND status = 'OPEN')";
        array_push($p, (int) $u['doctor_id'], (int) $u['doctor_id']);
    }
    $order = ['name' => 'p.full_name ASC', 'code' => 'p.patient_code ASC', 'recent' => 'p.created_at DESC'][$f['sort'] ?? 'name'] ?? 'p.full_name ASC';
    $where = implode(' AND ', $w);
    $total = (int) q_val("SELECT COUNT(*) FROM patient p WHERE $where", $p);
    $rows = q_all("SELECT p.*, (SELECT MAX(visited_at) FROM clinical_visit v WHERE v.patient_id = p.patient_id AND v.status <> 'CANCELLED') AS last_visit
                   FROM patient p WHERE $where ORDER BY $order LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $p);
    return [$rows, $total];
}
