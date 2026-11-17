<?php
declare(strict_types=1);
// Examination lifecycle: DRAFT -> FINALIZED -> SUPERSEDED (by a correction row). A draft may be DISCARDED. Nothing is deleted.
const EXAM_TEXT_COLUMNS = ['malocclusion_note', 'tmj_note', 'general_note'];
function exam_find(int $id): ?array { return q_one('SELECT e.*, u.display_name AS author_name FROM clinical_examination e JOIN user_account u ON u.user_id = e.authored_by WHERE e.exam_id = ?', [$id]); }
function exams_for_visit(int $visitId): array {
    return q_all('SELECT e.*, u.display_name AS author_name, f.display_name AS finalized_by_name FROM clinical_examination e JOIN user_account u ON u.user_id = e.authored_by
                  LEFT JOIN user_account f ON f.user_id = e.finalized_by WHERE e.visit_id = ? ORDER BY e.exam_id ASC', [$visitId]);
}
function exams_for_patient(int $pid, ?int $doctorId = null): array {
    return q_all("SELECT e.exam_id, e.status, e.examined_at, v.visit_no, v.visit_id, d.full_name AS doctor_name FROM clinical_examination e JOIN clinical_visit v ON v.visit_id = e.visit_id
                  JOIN doctor d ON d.doctor_id = e.doctor_id WHERE e.patient_id = ? AND e.status IN ('FINALIZED','SUPERSEDED','DRAFT') AND (? IS NULL OR e.doctor_id = ?) ORDER BY e.examined_at DESC, e.exam_id DESC", [$pid, $doctorId, $doctorId]);
}
/** The exam currently in force for a visit: a DRAFT or a FINALIZED row that nothing supersedes. */
function exam_current(int $visitId): ?array {
    return q_one("SELECT * FROM clinical_examination WHERE visit_id = ? AND status IN ('DRAFT','FINALIZED') ORDER BY exam_id DESC LIMIT 1", [$visitId]);
}
function exam_extras(int $examId): array {
    $o = []; foreach (q_all('SELECT field_key, field_value FROM clinical_examination_extra WHERE exam_id = ?', [$examId]) as $r) { $o[$r['field_key']] = $r['field_value']; } return $o;
}
/** @return array{0:array,1:array} column values and provisional extras */
function exam_validate(array $in, array &$err): array {
    $c = ['examined_at' => v_datetime($in, 'examined_at', $err, 'Examination date and time', ['required' => true])];
    if ($c['examined_at'] && $c['examined_at'] > date('Y-m-d H:i:s', time() + 300)) { $err['examined_at'] = 'The examination time cannot be in the future.'; }
    foreach (clinic('code_fields') as $col => [$lab, $opts]) { $c[$col] = v_enum($in, $col, $err, $lab, $opts); }
    $labels = ['malocclusion_note' => 'Malocclusion', 'tmj_note' => 'TMJ findings', 'general_note' => 'Clinical notes'];
    foreach (EXAM_TEXT_COLUMNS as $t) { $c[$t] = v_text($in, $t, $err, $labels[$t], ['max' => 3000, 'multiline' => true]); }
    $c['mouth_opening_mm'] = v_decimal($in, 'mouth_opening_mm', $err, 'Mouth opening (mm)', ['max' => 999]);
    $x = [];
    foreach (clinic('extra_fields') as $k => [$lab, $type]) {
        $x[$k] = $type === 'yesno' ? (v_enum($in, $k, $err, $lab, YESNO) ?? 'NOT_PROVIDED')
               : v_text($in, $k, $err, $lab, $type === 'textarea' ? ['max' => 3000, 'multiline' => true] : ['max' => 200]);
    }
    return [$c, $x];
}
function exam_write_extras(int $examId, array $x): void {
    foreach (clinic('extra_fields') as $k => $_) {
        q('INSERT INTO clinical_examination_extra (exam_id, field_key, field_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)', [$examId, $k, $x[$k] ?? null]);
    }
}
function exam_columns(): array { return array_merge(['examined_at', 'mouth_opening_mm'], array_keys(clinic('code_fields')), EXAM_TEXT_COLUMNS); }

function exam_save_draft(int $visitId, ?int $examId, array $c, array $x, int $userId): int {
    return tx(function () use ($visitId, $examId, $c, $x, $userId) {
        $v = q_one('SELECT * FROM clinical_visit WHERE visit_id = ? FOR UPDATE', [$visitId]);
        if (!$v) { throw new DomainException('Visit not found.'); }
        clinical_owner($v, $userId);
        if ($v['status'] !== 'OPEN') { throw new DomainException('Examinations can be created or edited only while the visit is open.'); }
        $cols = exam_columns();
        if ($examId === null) {
            if (exam_current($visitId)) { throw new DomainException('This visit already has a current examination. Edit the draft, or correct the finalized one.'); }
            q('INSERT INTO clinical_examination (visit_id, patient_id, doctor_id, authored_by, ' . implode(',', $cols) . ') VALUES (?,?,?,?,' . implode(',', array_fill(0, count($cols), '?')) . ')',
              array_merge([$visitId, $v['patient_id'], $v['doctor_id'], $userId], array_map(fn($k) => $c[$k] ?? null, $cols)));
            $examId = (int) db()->lastInsertId();
        } else {
            $e = q_one('SELECT * FROM clinical_examination WHERE exam_id = ? FOR UPDATE', [$examId]);
            if (!$e || (int) $e['visit_id'] !== $visitId) { throw new DomainException('Examination not found for this visit.'); }
            if ($e['status'] !== 'DRAFT') { throw new DomainException('Only a draft can be edited. Use "Correct examination" for a finalized one.'); }
            q('UPDATE clinical_examination SET ' . implode(' = ?, ', $cols) . ' = ? WHERE exam_id = ?', array_merge(array_map(fn($k) => $c[$k] ?? null, $cols), [$examId]));
        }
        exam_write_extras($examId, $x);
        audit('EXAM_DRAFT_SAVED', 'exam', $examId, 'SUCCESS', ['visit' => $visitId]);
        return $examId;
    });
}
function exam_finalize(int $examId, int $userId): void {
    tx(function () use ($examId, $userId) {
        $e = q_one('SELECT * FROM clinical_examination WHERE exam_id = ? FOR UPDATE', [$examId]);
        if (!$e) { throw new DomainException('Examination not found.'); }
        if ($e['status'] !== 'DRAFT') { throw new DomainException('Only a draft can be finalized.'); }
        $v = q_one('SELECT * FROM clinical_visit WHERE visit_id = ?', [$e['visit_id']]);
        clinical_owner($v, $userId);
        if ($v['status'] !== 'OPEN') { throw new DomainException('The visit is not open.'); }
        q("UPDATE clinical_examination SET status = 'FINALIZED', finalized_by = ?, finalized_at = NOW() WHERE exam_id = ?", [$userId, $examId]);
        audit('EXAM_FINALIZED', 'exam', $examId, 'SUCCESS', ['status_from' => 'DRAFT', 'status_to' => 'FINALIZED']);
    });
}
function exam_discard(int $examId, string $reasonCode, int $userId): void {
    tx(function () use ($examId, $reasonCode, $userId) {
        if (!array_key_exists($reasonCode, clinic('discard_reasons'))) { throw new DomainException('Choose a reason.'); }
        $e = q_one('SELECT * FROM clinical_examination WHERE exam_id = ? FOR UPDATE', [$examId]);
        if (!$e) { throw new DomainException('Examination not found.'); }
        clinical_owner(q_one('SELECT * FROM clinical_visit WHERE visit_id=?', [$e['visit_id']]), $userId);
        if ($e['status'] !== 'DRAFT') { throw new DomainException('Only a draft can be discarded. A finalized examination is corrected, never discarded.'); }
        q("UPDATE clinical_examination SET status = 'DISCARDED', discarded_by = ?, discarded_at = NOW(), discard_reason_code = ? WHERE exam_id = ?", [$userId, $reasonCode, $examId]);
        audit('EXAM_DISCARDED', 'exam', $examId, 'SUCCESS', ['status_from' => 'DRAFT', 'status_to' => 'DISCARDED', 'reason_code' => $reasonCode]);
    });
}
/** Correct a finalized examination: a NEW finalized row is added and the old one is marked SUPERSEDED (its content is untouched). */
function exam_correct(int $examId, array $c, array $x, string $reason, int $userId): int {
    return tx(function () use ($examId, $c, $x, $reason, $userId) {
        $old = q_one('SELECT * FROM clinical_examination WHERE exam_id = ? FOR UPDATE', [$examId]);
        if (!$old) { throw new DomainException('Examination not found.'); }
        if ($old['status'] !== 'FINALIZED') { throw new DomainException('Only a finalized examination that has not been corrected can be corrected.'); }
        $v = q_one('SELECT * FROM clinical_visit WHERE visit_id = ?', [$old['visit_id']]);
        clinical_owner($v, $userId, false);
        if ($v['status'] === 'CANCELLED') { throw new DomainException('This visit was cancelled.'); }
        if (q_val('SELECT 1 FROM clinical_examination WHERE supersedes_exam_id = ?', [$examId])) { throw new DomainException('This examination has already been corrected.'); }
        $cols = exam_columns();
        q('INSERT INTO clinical_examination (visit_id, patient_id, doctor_id, authored_by, status, supersedes_exam_id, correction_reason, finalized_by, finalized_at, ' . implode(',', $cols) . ')
           VALUES (?,?,?,?,?,?,?,?,NOW(),' . implode(',', array_fill(0, count($cols), '?')) . ')',
          array_merge([$old['visit_id'], $old['patient_id'], $old['doctor_id'], $userId, 'FINALIZED', $examId, $reason, $userId], array_map(fn($k) => $c[$k] ?? null, $cols)));
        $new = (int) db()->lastInsertId();
        exam_write_extras($new, $x);
        q("UPDATE clinical_examination SET status = 'SUPERSEDED' WHERE exam_id = ?", [$examId]);
        audit('EXAM_CORRECTED', 'exam', $new, 'SUCCESS', ['status_from' => 'FINALIZED', 'status_to' => 'SUPERSEDED']);
        return $new;
    });
}

// ---------- examination form and read-only display (shared by several pages) ----------
function exam_dt_input(?string $s): string { return $s ? date('Y-m-d\TH:i', strtotime($s)) : ''; }
function exam_section(string $title, bool $provisional, string $body): string {
    return '<fieldset class="exam-section"><legend>' . e($title) . ($provisional ? ' ' . badge('Provisional', 'warn') : '') . '</legend>' . $body . '</fieldset>';
}
function exam_extras_by_section(): array {
    $by = []; foreach (clinic('extra_fields') as $k => [$lab, $type, $sec, $hint]) { $by[$sec][$k] = [$lab, $type, $hint]; } return $by;
}
function exam_extra_controls(string $section, array $v, array $err): string {
    $h = '';
    foreach ((exam_extras_by_section()[$section] ?? []) as $k => [$lab, $type, $hint]) {
        if ($type === 'textarea') { $h .= f_textarea($k, $lab, $v, $err, ['rows' => 3, 'hint' => $hint]); }
        elseif ($type === 'yesno') { $h .= f_radio($k, $lab, ['NOT_PROVIDED' => 'Not provided', 'YES' => 'Yes', 'NO' => 'No', 'UNKNOWN' => 'Unknown'], [$k => $v[$k] ?? 'NOT_PROVIDED'], $err, ['class' => 'qrow']); }
        else { $h .= f_text($k, $lab, $v, $err, ['hint' => $hint, 'maxlength' => 200]); }
    }
    return $h;
}
function exam_form_fields(array $v, array $err, array $visit): string {
    $codes = '';
    foreach (clinic('code_fields') as $col => [$lab, $opts]) { $codes .= f_radio($col, $lab, ['' => 'Not recorded'] + array_combine($opts, $opts), [$col => $v[$col] ?? ''], $err, ['class' => 'qrow']); }
    $h = exam_section('Examination details', false, '<div class="grid-2">' . f_text('visit_ref', 'Visit reference', ['visit_ref' => $visit['visit_no']], [], ['readonly' => true])
        . f_text('doctor_ref', 'Responsible doctor', ['doctor_ref' => $visit['doctor_name']], [], ['readonly' => true])
        . f_text('examined_at', 'Examination date and time', $v, $err, ['type' => 'datetime-local', 'required' => true]) . '</div>');
    $h .= exam_section('Presenting complaint', true, exam_extra_controls('Presenting complaint', $v, $err));
    $h .= exam_section('Oral and dental examination', false, '<p class="hint">Codes are exactly as printed on the clinic form. Their meanings are not interpreted here.</p>' . $codes . f_textarea('malocclusion_note', 'Malocclusion', $v, $err, ['rows' => 2]));
    $h .= exam_section('Jaw, TMJ and function', false, '<div class="grid-2">' . f_text('mouth_opening_mm', 'Mouth opening (mm)', $v, $err, ['maxlength' => 7, 'hint' => 'Number, up to 2 decimals.']) . '</div>'
        . f_textarea('tmj_note', 'TMJ findings', $v, $err, ['rows' => 2]) . exam_extra_controls('Jaw, TMJ and function', $v, $err) . f_textarea('general_note', 'Clinical notes', $v, $err, ['rows' => 3]));
    $h .= exam_section('Clinical findings', true, exam_extra_controls('Clinical findings', $v, $err));
    $h .= exam_section('Assessment and plan', true, exam_extra_controls('Assessment and plan', $v, $err));
    return $h . '<p class="hint">Provisional sections are design additions for teacher and clinic review, not confirmed clinic requirements. They are configured in config/clinic_options.php. No digital signature is captured.</p>';
}
function exam_display(array $ex, array $x): string {
    $dl = function (array $rows) { return '<dl class="dl">' . implode('', $rows) . '</dl>'; };
    $ext = function (string $sec) use ($x) { $r = []; foreach ((exam_extras_by_section()[$sec] ?? []) as $k => [$lab, $type]) { $val = $x[$k] ?? null; $r[] = dl_row($lab, $type === 'yesno' ? ($val ? label($val) : null) : $val, $type === 'textarea'); } return $r; };
    $codes = []; foreach (clinic('code_fields') as $col => [$lab]) { $codes[] = dl_row($lab, $ex[$col]); }
    $h = '<div class="exam-read"><section><h3>Presenting complaint ' . badge('Provisional', 'warn') . '</h3>' . $dl($ext('Presenting complaint')) . '</section>';
    $h .= '<section><h3>Oral and dental examination</h3>' . $dl(array_merge($codes, [dl_row('Malocclusion', $ex['malocclusion_note'], true)])) . '</section>';
    $h .= '<section><h3>Jaw, TMJ and function</h3>' . $dl(array_merge([dl_row('Mouth opening', $ex['mouth_opening_mm'] !== null ? $ex['mouth_opening_mm'] . ' mm' : null), dl_row('TMJ findings', $ex['tmj_note'], true)], $ext('Jaw, TMJ and function'), [dl_row('Clinical notes', $ex['general_note'], true)])) . '</section>';
    $h .= '<section><h3>Clinical findings ' . badge('Provisional', 'warn') . '</h3>' . $dl($ext('Clinical findings')) . '</section>';
    $h .= '<section><h3>Assessment and plan ' . badge('Provisional', 'warn') . '</h3>' . $dl($ext('Assessment and plan')) . '</section></div>';
    return $h;
}
