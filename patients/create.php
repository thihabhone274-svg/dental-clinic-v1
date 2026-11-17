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
<label class="check"><input type="checkbox" name="confirm_different" value="1" form="patient-form"><span>I reviewed these records. This is a different person.</span></label>
<?php if (is_post() && !$err && !empty($dups) && empty($_POST['confirm_different'])): ?><div class="error-text">Tick the box to confirm, then press “Register patient”.</div><?php endif; ?></section>
<?php endif; ?>
<form id="patient-form" method="post" novalidate class="panel patient-form">
  <?= csrf_field() ?>
  <h2>Patient details</h2>
  <?php patient_fields($v, $err, true); ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit">Register patient</button> <a class="btn btn-secondary" href="<?= e(url('patients/index.php')) ?>">Cancel</a></div>
</form>
<?php page_end();
