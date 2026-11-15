<?php
declare(strict_types=1);
// Medical and dental history. Every save inserts a NEW version row; earlier versions are never modified.
const MED_STATUS = ['allergy_status' => ['NOT_ASKED', 'UNKNOWN', 'NONE_REPORTED', 'HAS_ALLERGIES', 'NOT_APPLICABLE'],
    'past_medical_history_status' => ['NOT_ASKED', 'UNKNOWN', 'NONE_REPORTED', 'DETAILS_REPORTED', 'NOT_APPLICABLE'],
    'medication_status' => ['NOT_ASKED', 'UNKNOWN', 'NONE_REPORTED', 'TAKING_MEDICATION', 'NOT_APPLICABLE']];
const YESNO = ['NOT_PROVIDED', 'YES', 'NO', 'UNKNOWN'];

function med_latest(int $pid): ?array {
    $r = q_one('SELECT m.*, u.display_name AS recorded_by_name FROM medical_history_version m JOIN user_account u ON u.user_id = m.recorded_by WHERE m.patient_id = ? ORDER BY m.version_no DESC LIMIT 1', [$pid]);
    if ($r) { $r['allergies'] = med_allergies((int) $r['history_id']); }
    return $r;
}
function med_get(int $pid, int $version): ?array {
    $r = q_one('SELECT m.*, u.display_name AS recorded_by_name FROM medical_history_version m JOIN user_account u ON u.user_id = m.recorded_by WHERE m.patient_id = ? AND m.version_no = ?', [$pid, $version]);
    if ($r) { $r['allergies'] = med_allergies((int) $r['history_id']); }
    return $r;
}
function med_allergies(int $historyId): array { return q_all('SELECT allergen_name, severity, reaction_note FROM allergy_item WHERE history_id = ? ORDER BY allergy_id', [$historyId]); }
function med_versions(int $pid): array {
    return q_all('SELECT m.version_no, m.recorded_at, u.display_name AS recorded_by_name FROM medical_history_version m JOIN user_account u ON u.user_id = m.recorded_by WHERE m.patient_id = ? ORDER BY m.version_no DESC', [$pid]);
}
/** @return array{0:array,1:array} cleaned fields and allergy rows */
function med_validate(array $in, array &$err): array {
    $d = [];
    foreach (MED_STATUS as $f => $allowed) { $d[$f] = v_enum($in, $f, $err, label($f), $allowed, ['required' => true]); }
    $d['past_medical_history'] = v_text($in, 'past_medical_history', $err, 'Past medical history', ['max' => 3000, 'multiline' => true]);
    $d['regular_medication'] = v_text($in, 'regular_medication', $err, 'Regularly taken medication', ['max' => 3000, 'multiline' => true]);
    $d['additional_note'] = v_text($in, 'additional_note', $err, 'Notes', ['max' => 3000, 'multiline' => true]);
    $names = (array) ($in['allergen'] ?? []); $sev = (array) ($in['severity'] ?? []); $rea = (array) ($in['reaction'] ?? []);
    $rows = [];
    foreach ($names as $i => $n) {
        $n = trim((string) preg_replace('/\s+/u', ' ', (string) $n)); $s = (string) ($sev[$i] ?? 'UNKNOWN'); $r = trim((string) ($rea[$i] ?? ''));
        if ($n === '' && $r === '') { continue; }
        if ($n === '') { $err['allergen'] = 'Each allergy row needs an allergen name.'; continue; }
        if (mb_strlen($n) > 180 || mb_strlen($r) > 500) { $err['allergen'] = 'Allergen (max 180) or reaction (max 500) is too long.'; continue; }
        if (!in_array($s, ['UNKNOWN', 'MILD', 'MODERATE', 'SEVERE'], true)) { $err['allergen'] = 'Choose a valid severity.'; continue; }
        $rows[] = ['allergen_name' => $n, 'severity' => $s, 'reaction_note' => $r === '' ? null : $r];
    }
    if (count($rows) > 30) { $err['allergen'] = 'At most 30 allergy rows.'; }
    if (!isset($err['allergy_status'])) {
        if ($d['allergy_status'] === 'HAS_ALLERGIES' && !$rows) { $err['allergy_status'] = 'Add at least one allergy, or change the status.'; }
        if ($d['allergy_status'] !== 'HAS_ALLERGIES' && $rows) { $err['allergy_status'] = 'Allergies are listed, so the status must be "Has allergies".'; }
    }
    if (!isset($err['past_medical_history_status'])) {
        if ($d['past_medical_history_status'] === 'DETAILS_REPORTED' && $d['past_medical_history'] === null) { $err['past_medical_history'] = 'Enter the details, or change the status.'; }
        if ($d['past_medical_history_status'] !== 'DETAILS_REPORTED' && $d['past_medical_history'] !== null) { $err['past_medical_history_status'] = 'Details were entered, so the status must be "Details reported".'; }
    }
    if (!isset($err['medication_status'])) {
        if ($d['medication_status'] === 'TAKING_MEDICATION' && $d['regular_medication'] === null) { $err['regular_medication'] = 'Enter the medication, or change the status.'; }
        if ($d['medication_status'] !== 'TAKING_MEDICATION' && $d['regular_medication'] !== null) { $err['medication_status'] = 'Medication was entered, so the status must be "Taking medication".'; }
    }
    return [$d, $rows];
}
/** @return int|null the new version number, or null when nothing changed */
function med_record(int $pid, array $d, array $rows, int $userId): ?int {
    return tx(function () use ($pid, $d, $rows, $userId) {
        $p = q_one('SELECT archived_at FROM patient WHERE patient_id = ? FOR UPDATE', [$pid]);
        if (!$p) { throw new DomainException('Patient not found.'); }
        if ($p['archived_at'] !== null) { throw new DomainException('This patient is archived and read-only.'); }
        $cur = med_latest($pid);
        if ($cur) {
            $same = true;
            foreach (['allergy_status', 'past_medical_history_status', 'past_medical_history', 'medication_status', 'regular_medication', 'additional_note'] as $f) { if ((string) $cur[$f] !== (string) ($d[$f] ?? '')) { $same = false; } }
            $a = array_map(fn($r) => [$r['allergen_name'], $r['severity'], (string) $r['reaction_note']], $cur['allergies']);
            $b = array_map(fn($r) => [$r['allergen_name'], $r['severity'], (string) $r['reaction_note']], $rows);
            if ($same && $a === $b) { return null; }
        }
        $ver = (int) q_val('SELECT COALESCE(MAX(version_no), 0) + 1 FROM medical_history_version WHERE patient_id = ?', [$pid]);
        q('INSERT INTO medical_history_version (patient_id, version_no, allergy_status, past_medical_history_status, past_medical_history, medication_status, regular_medication, additional_note, recorded_by)
           VALUES (?,?,?,?,?,?,?,?,?)', [$pid, $ver, $d['allergy_status'], $d['past_medical_history_status'], $d['past_medical_history'], $d['medication_status'], $d['regular_medication'], $d['additional_note'], $userId]);
        $hid = (int) db()->lastInsertId();
        foreach ($rows as $r) { q('INSERT INTO allergy_item (history_id, allergen_name, severity, reaction_note) VALUES (?,?,?,?)', [$hid, $r['allergen_name'], $r['severity'], $r['reaction_note']]); }
        audit('MED_HISTORY_VERSION_CREATED', 'patient', $pid, 'SUCCESS', ['version_no' => $ver]);
        return $ver;
    });
}

// ---------------- dental history ----------------
function dental_latest(int $pid): ?array {
    return q_one('SELECT d.*, u.display_name AS recorded_by_name FROM dental_history_version d JOIN user_account u ON u.user_id = d.recorded_by WHERE d.patient_id = ? ORDER BY d.version_no DESC LIMIT 1', [$pid]);
}
function dental_get(int $pid, int $version): ?array {
    return q_one('SELECT d.*, u.display_name AS recorded_by_name FROM dental_history_version d JOIN user_account u ON u.user_id = d.recorded_by WHERE d.patient_id = ? AND d.version_no = ?', [$pid, $version]);
}
function dental_versions(int $pid): array {
    return q_all('SELECT d.version_no, d.recorded_at, u.display_name AS recorded_by_name FROM dental_history_version d JOIN user_account u ON u.user_id = d.recorded_by WHERE d.patient_id = ? ORDER BY d.version_no DESC', [$pid]);
}
function dental_validate(array $in, array &$err): array {
    $d = ['last_dental_visit_text' => v_text($in, 'last_dental_visit_text', $err, 'Last dental visit', ['max' => 160]),
          'extraction_complication_note' => v_text($in, 'extraction_complication_note', $err, 'Extraction complication details', ['max' => 2000, 'multiline' => true]),
          'brushing_frequency' => v_enum($in, 'brushing_frequency', $err, 'Brushing frequency', clinic('brushing_options')),
          'additional_note' => v_text($in, 'additional_note', $err, 'Notes', ['max' => 3000, 'multiline' => true])];
    foreach (array_keys(clinic('dental_yesno')) as $f) { $d[$f] = v_enum($in, $f, $err, clinic('dental_yesno')[$f], YESNO) ?? 'NOT_PROVIDED'; }
    return $d;
}
function dental_record(int $pid, array $d, int $userId): ?int {
    return tx(function () use ($pid, $d, $userId) {
        $p = q_one('SELECT archived_at FROM patient WHERE patient_id = ? FOR UPDATE', [$pid]);
        if (!$p) { throw new DomainException('Patient not found.'); }
        if ($p['archived_at'] !== null) { throw new DomainException('This patient is archived and read-only.'); }
        $cols = array_merge(['last_dental_visit_text', 'extraction_complication_note', 'brushing_frequency', 'additional_note'], array_keys(clinic('dental_yesno')));
        $cur = dental_latest($pid);
        if ($cur) { $same = true; foreach ($cols as $c) { if ((string) $cur[$c] !== (string) ($d[$c] ?? '')) { $same = false; } } if ($same) { return null; } }
        $ver = (int) q_val('SELECT COALESCE(MAX(version_no), 0) + 1 FROM dental_history_version WHERE patient_id = ?', [$pid]);
        $names = implode(',', $cols);
        q("INSERT INTO dental_history_version (patient_id, version_no, $names, recorded_by) VALUES (?,?," . implode(',', array_fill(0, count($cols), '?')) . ',?)',
          array_merge([$pid, $ver], array_map(fn($c) => $d[$c] ?? null, $cols), [$userId]));
        audit('DENTAL_HISTORY_VERSION_CREATED', 'patient', $pid, 'SUCCESS', ['version_no' => $ver]);
        return $ver;
    });
}
