<?php
declare(strict_types=1);
// Small HTML builders so every form looks and behaves the same (labels, required marks, inline errors).
function field_wrap(string $name, string $label, string $control, array $err, array $o = []): string {
    $id = 'f_' . $name; $msg = $err[$name] ?? null;
    return '<div class="field' . ($msg ? ' has-error' : '') . (!empty($o['class']) ? ' ' . e($o['class']) : '') . '">'
        . '<label for="' . e($id) . '">' . e($label) . (!empty($o['required']) ? ' <span class="req" title="Required">*</span>' : '') . '</label>' . $control
        . (!empty($o['hint']) ? '<div class="hint">' . e($o['hint']) . '</div>' : '')
        . ($msg ? '<div class="error-text" id="' . e($id) . '_err" role="alert">' . e($msg) . '</div>' : '') . '</div>';
}
function f_text(string $name, string $label, array $v, array $err, array $o = []): string {
    $type = $o['type'] ?? 'text';
    $c = '<input class="input" id="f_' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($type === 'password' ? '' : ($v[$name] ?? '')) . '"'
       . (isset($o['maxlength']) ? ' maxlength="' . (int) $o['maxlength'] . '"' : '') . (!empty($o['readonly']) ? ' readonly' : '')
       . (isset($o['autocomplete']) ? ' autocomplete="' . e($o['autocomplete']) . '"' : '') . (isset($err[$name]) ? ' aria-invalid="true"' : '')
       . (isset($o['list']) ? ' list="' . e($o['list']) . '"' : '') . (isset($o['step']) ? ' step="' . e($o['step']) . '"' : '') . '>';
    return field_wrap($name, $label, $c, $err, $o);
}
function f_textarea(string $name, string $label, array $v, array $err, array $o = []): string {
    $c = '<textarea class="input" id="f_' . e($name) . '" name="' . e($name) . '" rows="' . (int) ($o['rows'] ?? 3) . '"' . (isset($err[$name]) ? ' aria-invalid="true"' : '') . '>' . e($v[$name] ?? '') . '</textarea>';
    return field_wrap($name, $label, $c, $err, $o);
}
function f_select(string $name, string $label, array $options, array $v, array $err, array $o = []): string {
    $cur = (string) ($v[$name] ?? '');
    $h = '<select class="input" id="f_' . e($name) . '" name="' . e($name) . '"' . (isset($err[$name]) ? ' aria-invalid="true"' : '') . '>';
    if (isset($o['placeholder'])) { $h .= '<option value="">' . e($o['placeholder']) . '</option>'; }
    foreach ($options as $val => $text) { $h .= '<option value="' . e($val) . '"' . ((string) $val === $cur ? ' selected' : '') . '>' . e($text) . '</option>'; }
    return field_wrap($name, $label, $h . '</select>', $err, $o);
}
/** Segmented radio group; an empty-valued first option can be supplied by the caller. */
function f_radio(string $name, string $label, array $options, array $v, array $err, array $o = []): string {
    $cur = (string) ($v[$name] ?? ''); $h = '<div class="radio-row" role="radiogroup" aria-label="' . e($label) . '" id="f_' . e($name) . '">';
    foreach ($options as $val => $text) {
        $h .= '<label class="radio"><input type="radio" name="' . e($name) . '" value="' . e((string) $val) . '"' . ((string) $val === $cur ? ' checked' : '') . '><span>' . e($text) . '</span></label>';
    }
    return field_wrap($name, $label, $h . '</div>', $err, $o);
}
function f_check(string $name, string $label, bool $checked = false): string {
    return '<label class="check"><input type="checkbox" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '><span>' . $label . '</span></label>';
}
function badge(string $text, string $cls = 'muted'): string { return '<span class="badge badge-' . e($cls) . '">' . e($text) . '</span>'; }
function status_badge(string $s): string {
    $m = ['SCHEDULED' => 'info', 'CONFIRMED' => 'ok', 'COMPLETED' => 'ok', 'CANCELLED' => 'danger', 'NO_SHOW' => 'warn', 'OPEN' => 'warn', 'CLOSED' => 'ok',
          'DRAFT' => 'warn', 'FINALIZED' => 'ok', 'SUPERSEDED' => 'muted', 'DISCARDED' => 'muted', 'PENDING' => 'warn', 'APPROVED' => 'ok',
          'REJECTED' => 'danger', 'ACTIVE' => 'ok', 'DISABLED' => 'danger', 'ARCHIVED' => 'muted', 'SUCCESS' => 'ok', 'DENIED' => 'danger', 'FAILED' => 'danger'];
    return badge(label($s), $m[$s] ?? 'muted');
}
/** A POST form button with a confirmation dialog (handled by assets/js/app.js; the server still validates). */
function post_button(string $action, array $hidden, string $text, string $cls = 'btn-secondary', string $confirm = ''): string {
    $h = '<form method="post" action="' . e(url($action)) . '" class="inline"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
    foreach ($hidden as $k => $v) { $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">'; }
    return $h . '<button type="submit" class="btn ' . e($cls) . ' btn-sm">' . e($text) . '</button></form>';
}

/** Shared patient header + tabs used by the profile and history pages. */
function patient_header(array $p, string $tab): void {
    $archived = $p['archived_at'] !== null; $id = (int) $p['patient_id'];
    echo '<div class="patient-head"><div class="avatar" aria-hidden="true">' . e(mb_strtoupper(mb_substr($p['full_name'], 0, 1))) . '</div><div><h2>' . e($p['full_name']) . ($archived ? ' ' . badge('Archived', 'muted') : '') . '</h2>'
       . '<p class="muted">' . e($p['patient_code']) . ($p['date_of_birth'] ? ' · born ' . e($p['date_of_birth']) . ' (age ' . e(age_from($p['date_of_birth'])) . ')' : '') . ($p['phone'] ? ' · ' . e($p['phone']) : '') . '</p></div></div>';
    if ($archived) { echo '<div class="alert alert-warn">ARCHIVED, read-only. No new appointments, visits or history can be added, and the record cannot be edited. (Archive restore is not available in this version.)</div>'; }
    $tabs = ['overview' => ['Overview', "patients/view.php?id=$id"], 'medical' => ['Medical history', "histories/medical.php?patient_id=$id"],
             'dental' => ['Dental history', "histories/dental.php?patient_id=$id"], 'appointments' => ['Appointments', "patients/view.php?id=$id&tab=appointments"], 'visits' => ['Visits and examinations', "patients/view.php?id=$id&tab=visits"]];
    echo '<div class="tabs">';
    foreach ($tabs as $k => [$t, $href]) { echo '<a class="' . ($tab === $k ? 'on' : '') . '" href="' . e(url($href)) . '">' . e($t) . '</a>'; }
    echo '</div>';
}
/** Definition-list row for read-only clinical information. */
function dl_row(string $label, ?string $value, bool $pre = false): string {
    $v = ($value === null || $value === '') ? '<span class="muted">Not recorded</span>' : ($pre ? '<span class="pre">' . e($value) . '</span>' : e($value));
    return '<div class="dl-row"><dt>' . e($label) . '</dt><dd>' . $v . '</dd></div>';
}
