<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_can('patient.write');
$id = is_post() ? post_int('id') : get_int('id');
$p = patient_find($id);
if (!$p) { abort(404, 'Not found', 'That patient record does not exist.'); }
if ($p['archived_at'] !== null) { flash('warn', 'This patient is archived and cannot be edited.'); redirect('patients/view.php?id=' . $id); }
$err = []; $v = $p;
if (is_post()) {
    csrf_check();
    $v = $_POST;
    $d = patient_validate($_POST, $err, false, $p);
    if (!$err) {
        try {
            $changed = patient_update($id, $d, (int) $u['user_id']);
            flash('success', $changed ? 'Patient details updated.' : 'No changes were made.');
            if ($changed && ($dups = patient_duplicates($d, $id))) { flash('warn', 'This record now resembles ' . $dups[0]['patient_code'] . ' (' . implode(', ', $dups[0]['reasons']) . '). Please review for duplicates.'); }
            redirect('patients/view.php?id=' . $id);
        } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    }
}
page_start('Edit patient', ['nav' => 'patients', 'crumbs' => [['Patients', 'patients/index.php'], [$p['full_name'], 'patients/view.php?id=' . $id]], 'subtitle' => $p['patient_code'] . ' · registered ' . $p['registration_date']]);
if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was saved.</div><?php endif; ?>
<form id="patient-form" method="post" novalidate class="panel patient-form">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
  <?php patient_fields($v, $err, false); ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit">Save changes</button> <a class="btn btn-secondary" href="<?= e(url('patients/view.php?id=' . $id)) ?>">Cancel</a></div>
</form>
<?php page_end();
