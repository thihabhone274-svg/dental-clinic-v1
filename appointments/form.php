<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_can('appointment.write');
$id = is_post() ? post_int('id') : get_int('id');
$appt = $id ? appt_find($id) : null;
if ($id && !$appt) { abort(404, 'Not found', 'That appointment does not exist.'); }
if ($appt && (!in_array($appt['status'], ['SCHEDULED', 'CONFIRMED'], true) || appt_has_blocking_visit($id))) { flash('warn', 'This appointment can no longer be rescheduled.'); redirect('appointments/view.php?id=' . $id); }
$pid = $appt ? (int) $appt['patient_id'] : (is_post() ? post_int('patient_id') : get_int('patient_id'));
$patient = $pid ? patient_find($pid) : null;
// Step 1 (new booking without a patient): search for the patient
if (!$patient && !$appt) {
    $q = trim((string) ($_GET['q'] ?? '')); $res = [];
    if ($q !== '') { [$res] = patient_search(['q' => $q, 'status' => 'active'], 10, 0); }
    page_start('Book appointment', ['nav' => 'appointments', 'crumbs' => [['Appointments', 'appointments/index.php']], 'subtitle' => 'Step 1 of 2: find the patient.']);
    ?><form method="get" class="filters"><?= f_text('q', 'Patient name, registration number or telephone', ['q' => $q], [], ['maxlength' => 100]) ?><div class="field"><label>&nbsp;</label><button class="btn btn-primary">Search</button></div></form>
    <?php if ($q !== '' && !$res): ?><div class="empty-state small"><p>No matching active patient. <a href="<?= e(url('patients/create.php')) ?>">Register a new patient</a>.</p></div><?php endif;
    if ($res): ?><div class="table-wrap"><table class="table"><thead><tr><th>Reg. no.</th><th>Name</th><th>Date of birth</th><th></th></tr></thead><tbody>
    <?php foreach ($res as $r): ?><tr><td><?= e($r['patient_code']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e(fmt_date($r['date_of_birth'])) ?></td><td><a class="btn btn-secondary btn-sm" href="?patient_id=<?= (int) $r['patient_id'] ?>">Select</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif;
    page_end(); exit;
}
if (!$patient) { abort(404, 'Not found', 'Patient not found.'); }
if ($patient['archived_at'] !== null) { flash('warn', 'Archived patients cannot be given appointments.'); redirect('patients/view.php?id=' . $pid); }
$err = []; $warn = false;
$v = $appt ? ['doctor_id' => $appt['doctor_id'], 'appt_date' => substr($appt['start_at'], 0, 10), 'start_time' => substr($appt['start_at'], 11, 5), 'end_time' => substr($appt['end_at'], 11, 5), 'reason' => $appt['reason'], 'note' => $appt['note']] : ['appt_date' => date('Y-m-d')];
if (is_post()) {
    csrf_check();
    $v = $_POST;
    $input = $_POST; if ($appt) { $input['doctor_id'] = $appt['doctor_id']; }
    $d = appt_validate($input, $err, false); $d['patient_id'] = $pid;
    $reason = null;
    if ($appt) { $reason = v_text($_POST, 'change_reason', $err, 'Reason for rescheduling', ['required' => true, 'max' => 500]); }
    if (!$err) {
        try {
            if ($appt) { appt_reschedule($id, $d, (string) $reason, (int) $u['user_id'], !empty($_POST['confirm_overlap'])); flash('success', 'Appointment rescheduled. The previous time is kept in its history.'); redirect('appointments/view.php?id=' . $id); }
            $nid = appt_create($d, (int) $u['user_id'], !empty($_POST['confirm_overlap']));
            flash('success', 'Appointment booked.'); redirect('appointments/view.php?id=' . $nid);
        } catch (OverlapWarning $w) { $warn = true; flash('warn', $w->getMessage()); }
        catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    }
}
$warn = $warn || !empty($_POST['confirm_overlap']);
page_start($appt ? 'Reschedule appointment' : 'Book appointment', ['nav' => 'appointments', 'crumbs' => [['Appointments', 'appointments/index.php']], 'subtitle' => $patient['full_name'] . ' · ' . $patient['patient_code']]);
if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was saved.</div><?php endif; ?>
<form method="post" novalidate class="panel">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="patient_id" value="<?= (int) $pid ?>">
  <div class="grid-2">
    <?php if ($appt): ?><p><strong>Doctor:</strong> <?= e($appt['doctor_name']) ?>. Use Reassign doctor to change this.</p><?php else: ?><?= f_select('doctor_id', 'Doctor', doctor_options(), $v, $err, ['required' => true, 'placeholder' => 'Choose a doctor']) ?><?php endif; ?>
    <?= f_text('appt_date', 'Date', $v, $err, ['type' => 'date', 'required' => true]) ?>
    <?= f_text('start_time', 'Start time', $v, $err, ['type' => 'time', 'required' => true]) ?>
    <p class="hint">Minimum spacing: <?= appt_gap() ?> minutes between appointment starts. End time is calculated automatically.</p>
    <?php if (!$appt): ?><?= f_text('reason', 'Reason for appointment', $v, $err, ['maxlength' => 255]) ?><?php endif; ?>
  </div>
  <?php if (!$appt): ?><?= f_textarea('note', 'Note', $v, $err, ['rows' => 2]) ?><?php endif; ?>
  <?php if ($appt): ?><?= f_textarea('change_reason', 'Reason for rescheduling', $v, $err, ['required' => true, 'rows' => 2, 'hint' => 'Administrative reason only (for example “patient asked to move”). Do not enter clinical details.']) ?><?php endif; ?>
  <?php if ($warn): ?><div class="alert alert-warn"><?= f_check('confirm_overlap', 'This patient already has another appointment at that time. <strong>Book anyway.</strong>', !empty($_POST['confirm_overlap'])) ?></div><?php endif; ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit"><?= $appt ? 'Save new time' : 'Book appointment' ?></button> <a class="btn btn-secondary" href="<?= e(url($appt ? 'appointments/view.php?id=' . $id : 'appointments/index.php')) ?>">Cancel</a></div>
</form>
<?php page_end();
