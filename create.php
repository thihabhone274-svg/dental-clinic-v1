<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_can('patient.write');
$err = []; $v = ['registration_date' => date('Y-m-d')]; $dups = [];
if (is_post()) {
    csrf_check();
    $v = $_POST;
    $d = patient_validate($_POST, $err, true);
    if (!$err) {
        $dups = patient_duplicates($d);
        $reviewed = !empty($_POST['confirm_different']);
        if (!$dups || $reviewed) {
            try { $id = patient_create($d, (int) $u['user_id'], (bool) $dups); flash('success', 'Patient registered. No history has been recorded yet.'); redirect('patients/view.php?id=' . $id); }
            catch (DomainException $ex) { flash('error', $ex->getMessage()); }
        }
    }
}
page_start('Register patient', ['nav' => 'patients', 'crumbs' => [['Patients', 'patients/index.php']], 'subtitle' => 'The registration number is assigned automatically when the record is saved.']);
if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was saved.</div><?php endif;
if ($dups): ?>
<section class="panel warn-panel"><h2>Possible duplicate patients</h2>
<p>Review these records before saving. Nothing has been saved yet, and records are never merged automatically. A matching name alone does not mean the same person.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>Reg. no.</th><th>Name</th><th>Date of birth</th><th>Telephone</th><th>Why it matches</th></tr></thead><tbody>
<?php foreach ($dups as $r): ?><tr><td><a href="<?= e(url('patients/view.php?id=' . $r['patient_id'])) ?>" target="_blank" rel="noopener"><?= e($r['patient_code']) ?></a></td><td><?= e($r['full_name']) ?></td><td><?= e(fmt_date($r['date_of_birth'])) ?></td><td><?= e($r['phone'] ?? '') ?></td>
<td><?= badge(ucfirst($r['strength']), $r['strength'] === 'strong' ? 'warn' : 'muted') ?> <?= e(implode(', ', $r['reasons'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= f_check('confirm_different', 'I have reviewed these records: this is a <strong>different person</strong>, register as a new patient.') ?>
<?php if (is_post() && !$err && !empty($dups) && empty($_POST['confirm_different'])): ?><div class="error-text">Tick the box to confirm, then press “Register patient”.</div><?php endif; ?></section>
<?php endif; ?>
<form method="post" novalidate class="panel">
  <?= csrf_field() ?>
  <h2>Patient details</h2>
  <div class="grid-2">
    <?= f_text('full_name', 'Patient name', $v, $err, ['required' => true, 'maxlength' => 160]) ?>
    <?= f_text('date_of_birth', 'Date of birth', $v, $err, ['type' => 'date']) ?>
    <?= f_text('gender', 'Gender', $v, $err, ['maxlength' => 40]) ?>
    <?= f_text('occupation', 'Occupation', $v, $err, ['maxlength' => 120]) ?>
    <?= f_text('nationality', 'Nationality', $v, $err, ['maxlength' => 80]) ?>
    <?= f_text('phone', 'Telephone number', $v, $err, ['maxlength' => 40]) ?>
    <?= f_text('nrc_number', 'NRC (optional)', $v, $err, ['maxlength' => 80, 'hint' => 'Optional. Never used to identify the record.']) ?>
    <?= f_text('registration_date', 'Registration date', $v, $err, ['type' => 'date', 'required' => true]) ?>
  </div>
  <?= f_textarea('address', 'Address', $v, $err, ['rows' => 3]) ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit">Register patient</button> <a class="btn btn-secondary" href="<?= e(url('patients/index.php')) ?>">Cancel</a></div>
</form>
<?php page_end();
