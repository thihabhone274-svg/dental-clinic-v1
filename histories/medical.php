<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pid = is_post() ? post_int('patient_id') : get_int('patient_id');
if (is_post()) { require_post(); require_can('history.write'); }   // doctors are refused here: histories are read-only for them
$p = require_patient_access($pid);
$canWrite = can('history.write') && $p['archived_at'] === null;
$err = []; $post = null; $showForm = $canWrite && !empty($_GET['edit']);
if (is_post()) {
    $post = $_POST; $showForm = true;
    [$d, $rows] = med_validate($_POST, $err);
    if (!$err) {
        try {
            $ver = med_record($pid, $d, $rows, (int) $u['user_id']);
            flash($ver ? 'success' : 'info', $ver ? "Medical history saved as version $ver. Earlier versions are unchanged." : 'No changes were made, so no new version was created.');
            redirect('histories/medical.php?patient_id=' . $pid);
        } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    }
}
$versions = med_versions($pid);
$ver = get_int('version');
$cur = $ver ? med_get($pid, $ver) : med_latest($pid);
$latest = med_latest($pid);
// form values: submitted data on error, otherwise the latest version as a starting point
$fv = $post ?? ($latest ? $latest : []);
$rowsForForm = $post ? array_map(null, (array) ($post['allergen'] ?? []), (array) ($post['severity'] ?? []), (array) ($post['reaction'] ?? [])) : array_map(fn($a) => [$a['allergen_name'], $a['severity'], $a['reaction_note']], $latest['allergies'] ?? []);
$rowsForForm = array_values(array_filter($rowsForForm, fn($r) => trim((string) $r[0]) !== '' || trim((string) $r[2]) !== ''));
while (count($rowsForForm) < 2) { $rowsForForm[] = ['', 'UNKNOWN', '']; }
page_start('Medical history', ['nav' => 'patients', 'crumbs' => [['Patients', 'patients/index.php'], [$p['full_name'], 'patients/view.php?id=' . $pid]],
    'actions' => ($canWrite && !$showForm) ? '<a class="btn btn-primary" href="?patient_id=' . $pid . '&edit=1">Record new version</a>' : '']);
patient_header($p, 'medical');
if (!can('history.write')): ?><div class="alert alert-info">You can view medical history but not change it. Medical history is recorded by staff or an administrator.</div><?php endif;
if ($showForm && $canWrite):
  if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was saved.</div><?php endif; ?>
<form method="post" novalidate class="panel">
  <?= csrf_field() ?><input type="hidden" name="patient_id" value="<?= (int) $pid ?>">
  <h2>Record a new medical history version</h2><p class="muted">Saving creates a new version. Earlier versions are kept unchanged. “Not asked” is different from “None reported”.</p>
  <h3>Allergies</h3>
  <?= f_radio('allergy_status', 'Allergy status', array_combine(MED_STATUS['allergy_status'], array_map('label', MED_STATUS['allergy_status'])), $fv, $err, ['required' => true]) ?>
  <div class="field <?= isset($err['allergen']) ? 'has-error' : '' ?>"><label>Allergies (one per row)</label>
    <div id="allergy-rows"><?php foreach ($rowsForForm as $r): ?>
      <div class="allergy-row"><input class="input" name="allergen[]" placeholder="Allergen" maxlength="180" value="<?= e($r[0]) ?>">
        <select class="input" name="severity[]"><?php foreach (['UNKNOWN', 'MILD', 'MODERATE', 'SEVERE'] as $s): ?><option value="<?= $s ?>" <?= $s === ($r[1] ?: 'UNKNOWN') ? 'selected' : '' ?>><?= e(label($s)) ?></option><?php endforeach; ?></select>
        <input class="input" name="reaction[]" placeholder="Reaction (optional)" maxlength="500" value="<?= e($r[2]) ?>"></div><?php endforeach; ?></div>
    <button type="button" class="btn btn-ghost btn-sm" data-add-allergy>+ Add another allergy</button>
    <?php if (isset($err['allergen'])): ?><div class="error-text" role="alert"><?= e($err['allergen']) ?></div><?php endif; ?></div>
  <h3>Past medical history</h3>
  <?= f_radio('past_medical_history_status', 'Status', array_combine(MED_STATUS['past_medical_history_status'], array_map('label', MED_STATUS['past_medical_history_status'])), $fv, $err, ['required' => true]) ?>
  <?= f_textarea('past_medical_history', 'Details', $fv, $err, ['rows' => 3]) ?>
  <h3>Regularly taken medication</h3>
  <?= f_radio('medication_status', 'Status', array_combine(MED_STATUS['medication_status'], array_map('label', MED_STATUS['medication_status'])), $fv, $err, ['required' => true]) ?>
  <?= f_textarea('regular_medication', 'Medication', $fv, $err, ['rows' => 3]) ?>
  <h3>Notes</h3>
  <?= f_textarea('additional_note', 'Free-text medical history note', $fv, $err, ['rows' => 3, 'hint' => 'The additional note area on the clinic’s medical history form.']) ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit" data-confirm-msg>Save new version</button> <a class="btn btn-secondary" href="?patient_id=<?= (int) $pid ?>">Cancel</a></div>
</form>
<?php endif; ?>
<?php if (!$cur): ?><div class="empty-state"><h2>No medical history recorded</h2><p>No medical history has been recorded for this patient yet.</p><?php if ($canWrite): ?><a class="btn btn-primary" href="?patient_id=<?= (int) $pid ?>&edit=1">Record medical history</a><?php endif; ?></div>
<?php else: ?>
<div class="grid-main-side">
<section class="panel"><div class="row-between"><h2>Version <?= (int) $cur['version_no'] ?> <?= (int) $cur['version_no'] === (int) $latest['version_no'] ? badge('Current', 'ok') : badge('Earlier version', 'muted') ?></h2></div>
<p class="muted">Recorded <?= e(fmt_dt($cur['recorded_at'])) ?> by <?= e($cur['recorded_by_name']) ?></p>
<dl class="dl"><?= dl_row('Allergy status', label($cur['allergy_status'])) ?>
<?php if ($cur['allergies']): ?><div class="dl-row"><dt>Allergies</dt><dd><ul class="plain"><?php foreach ($cur['allergies'] as $a): ?><li><strong><?= e($a['allergen_name']) ?></strong> <?= badge(label($a['severity']), $a['severity'] === 'SEVERE' ? 'danger' : 'warn') ?> <?= e($a['reaction_note'] ?? '') ?></li><?php endforeach; ?></ul></dd></div><?php endif; ?>
<?= dl_row('Past medical history status', label($cur['past_medical_history_status'])) ?><?= dl_row('Past medical history', $cur['past_medical_history'], true) ?>
<?= dl_row('Medication status', label($cur['medication_status'])) ?><?= dl_row('Regularly taken medication', $cur['regular_medication'], true) ?><?= dl_row('Notes', $cur['additional_note'], true) ?></dl></section>
<aside class="panel"><h3>Versions</h3><ul class="plain versions"><?php foreach ($versions as $x): ?><li><a class="<?= (int) $x['version_no'] === (int) $cur['version_no'] ? 'on' : '' ?>" href="?patient_id=<?= (int) $pid ?>&version=<?= (int) $x['version_no'] ?>">Version <?= (int) $x['version_no'] ?></a><span class="muted"><?= e(fmt_date($x['recorded_at'])) ?> · <?= e($x['recorded_by_name']) ?></span></li><?php endforeach; ?></ul></aside></div>
<?php endif; ?>
<template id="allergy-row-tpl"><div class="allergy-row"><input class="input" name="allergen[]" placeholder="Allergen" maxlength="180"><select class="input" name="severity[]"><?php foreach (['UNKNOWN', 'MILD', 'MODERATE', 'SEVERE'] as $s): ?><option value="<?= $s ?>"><?= e(label($s)) ?></option><?php endforeach; ?></select><input class="input" name="reaction[]" placeholder="Reaction (optional)" maxlength="500"></div></template>
<?php page_end();
