<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_can('visit.open');
$err = []; $v = [];
if (is_post() && post_int('appointment_id') > 0) {                       // open from an appointment
    require_post();
    $a = appt_find(post_int('appointment_id'));
    if (!$a) { abort(404, 'Not found', 'That appointment does not exist.'); }
    if (!has_role('ADMIN') && !has_role('STAFF') && (int) $a['doctor_id'] !== (int) $u['doctor_id']) { deny('You can open visits only from your own appointments.'); }
    try { [$vid, $new] = visit_open_from_appointment((int) $a['appointment_id'], (int) $u['user_id'], !empty($_POST['confirm_other_date'])); flash($new ? 'success' : 'info', $new ? 'Visit opened.' : 'A visit already exists for this appointment.'); redirect('visits/view.php?id=' . $vid); }
    catch (DomainException $ex) { flash('warn', $ex->getMessage()); redirect('appointments/view.php?id=' . (int) $a['appointment_id']); }
}
$pid = is_post() ? post_int('patient_id') : get_int('patient_id');
$patient = $pid ? patient_find($pid) : null;
$docs = doctor_options();
$selfOnly = !has_role('ADMIN') && !has_role('STAFF');
if ($selfOnly) { $docs = array_intersect_key($docs, [(int) $u['doctor_id'] => true]); }
if ($patient && $selfOnly && !can_access_patient($pid)) { deny('This patient is not in your active care.'); }
if (is_post() && $patient) {                                              // walk-in
    require_post();
    $type = v_enum($_POST, 'visit_type', $err, 'Visit type', ['WALK_IN', 'EMERGENCY'], ['required' => true]);
    $doc = (int) ($_POST['doctor_id'] ?? 0); if (!isset($docs[$doc])) { $err['doctor_id'] = 'Choose a responsible doctor.'; }
    $note = v_text($_POST, 'note', $err, 'Reception note', ['max' => 2000, 'multiline' => true]);
    $v = $_POST;
    if (!$err) {
        try { $vid = visit_open_walkin($pid, $doc, (string) $type, $note, (int) $u['user_id']); flash('success', 'Walk-in visit opened.'); redirect('visits/view.php?id=' . $vid); }
        catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    }
}
if (!$patient) {
    $q = trim((string) ($_GET['q'] ?? '')); $res = []; if ($q !== '') { [$res] = patient_search(['q' => $q, 'status' => 'active'], 10, 0); }
    page_start('Open walk-in visit', ['nav' => 'visits', 'crumbs' => [['Visits', 'visits/index.php']], 'subtitle' => 'Step 1 of 2: find the patient. To open a visit from an appointment, use the appointment page.']);
    ?><form method="get" class="filters"><?= f_text('q', 'Patient name, registration number or telephone', ['q' => $q], [], ['maxlength' => 100]) ?><div class="field"><label>&nbsp;</label><button class="btn btn-primary">Search</button></div></form>
    <?php if ($q !== '' && !$res): ?><div class="empty-state small"><p>No matching active patient<?= can('patient.write') ? '. <a href="' . e(url('patients/create.php')) . '">Register a new patient</a>' : '' ?>.</p></div><?php endif;
    if ($res): ?><div class="table-wrap"><table class="table"><thead><tr><th>Reg. no.</th><th>Name</th><th>Date of birth</th><th></th></tr></thead><tbody><?php foreach ($res as $r): ?><tr><td><?= e($r['patient_code']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e(fmt_date($r['date_of_birth'])) ?></td><td><a class="btn btn-secondary btn-sm" href="?patient_id=<?= (int) $r['patient_id'] ?>">Select</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif;
    page_end(); exit;
}
if ($patient['archived_at'] !== null) { flash('warn', 'This patient is archived and read-only.'); redirect('patients/view.php?id=' . $pid); }
page_start('Open walk-in visit', ['nav' => 'visits', 'crumbs' => [['Visits', 'visits/index.php']], 'subtitle' => $patient['full_name'] . ' · ' . $patient['patient_code']]);
if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was saved.</div><?php endif; ?>
<form method="post" novalidate class="panel"><?= csrf_field() ?><input type="hidden" name="patient_id" value="<?= (int) $pid ?>">
 <?= f_radio('visit_type', 'Visit type', ['WALK_IN' => 'Walk-in', 'EMERGENCY' => 'Emergency'], $v + ['visit_type' => 'WALK_IN'], $err, ['required' => true]) ?>
 <?= f_select('doctor_id', 'Responsible doctor', $docs, $v + ['doctor_id' => count($docs) === 1 ? array_key_first($docs) : ''], $err, ['required' => true, 'placeholder' => 'Choose a doctor']) ?>
 <?= f_textarea('note', 'Reception note', $v, $err, ['rows' => 2]) ?>
 <div class="form-actions"><button class="btn btn-primary">Open visit</button> <a class="btn btn-secondary" href="<?= e(url('visits/index.php')) ?>">Cancel</a></div></form>
<?php page_end();
