<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$pid = is_post() ? post_int('patient_id') : get_int('patient_id');
if (is_post()) { require_post(); require_can('history.write'); }
$p = require_patient_access($pid);
$canWrite = can('history.write') && $p['archived_at'] === null;
$err = []; $post = null; $showForm = $canWrite && !empty($_GET['edit']);
if (is_post()) {
    $post = $_POST; $showForm = true;
    $d = dental_validate($_POST, $err);
    if (!$err) {
        try {
            $ver = dental_record($pid, $d, (int) $u['user_id']);
            flash($ver ? 'success' : 'info', $ver ? "Dental history saved as version $ver. Earlier versions are unchanged." : 'No changes were made, so no new version was created.');
            redirect('histories/dental.php?patient_id=' . $pid);
        } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    }
}
$versions = dental_versions($pid); $ver = get_int('version');
$cur = $ver ? dental_get($pid, $ver) : dental_latest($pid); $latest = dental_latest($pid);
$fv = $post ?? ($latest ?: []);
$yn = ['YES' => 'Yes', 'NO' => 'No', 'UNKNOWN' => 'Unknown', 'NOT_PROVIDED' => 'Not provided'];
page_start('Dental history', ['nav' => 'patients', 'crumbs' => [['Patients', 'patients/index.php'], [$p['full_name'], 'patients/view.php?id=' . $pid]],
    'actions' => ($canWrite && !$showForm) ? '<a class="btn btn-primary" href="?patient_id=' . $pid . '&edit=1">Record new version</a>' : '']);
patient_header($p, 'dental');
if (!can('history.write')): ?><div class="alert alert-info">You can view dental history but not change it. Dental history is recorded by staff or an administrator.</div><?php endif;
if ($showForm && $canWrite):
  if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was saved.</div><?php endif; ?>
<form method="post" novalidate class="panel">
  <?= csrf_field() ?><input type="hidden" name="patient_id" value="<?= (int) $pid ?>">
  <h2>Record a new dental history version</h2><p class="muted">Saving creates a new version; earlier versions are kept unchanged. “Not provided” is different from “No”. Question wording is provisional until checked against the clinic form.</p>
  <div class="grid-2"><?= f_text('last_dental_visit_text', 'Last dental visit', $fv, $err, ['maxlength' => 160, 'hint' => 'For example: 6 months ago']) ?>
  <?= f_select('brushing_frequency', 'Brushing frequency', array_combine(clinic('brushing_options'), clinic('brushing_options')), $fv, $err, ['placeholder' => 'Not provided']) ?></div>
  <h3>Questionnaire</h3>
  <?php foreach (clinic('dental_yesno') as $k => $lab): $fv[$k] = $fv[$k] ?? 'NOT_PROVIDED'; ?><?= f_radio($k, $lab, $yn, $fv, $err, ['class' => 'qrow']) ?>
    <?php if ($k === 'extraction_complications'): ?><?= f_textarea('extraction_complication_note', 'Extraction complication details', $fv, $err, ['rows' => 2]) ?><?php endif; endforeach; ?>
  <?= f_textarea('additional_note', 'Notes', $fv, $err, ['rows' => 3]) ?>
  <div class="form-actions"><button class="btn btn-primary" type="submit">Save new version</button> <a class="btn btn-secondary" href="?patient_id=<?= (int) $pid ?>">Cancel</a></div>
</form>
<?php endif; ?>
<?php if (!$cur): ?><div class="empty-state"><h2>No dental history recorded</h2><p>No dental history has been recorded for this patient yet.</p><?php if ($canWrite): ?><a class="btn btn-primary" href="?patient_id=<?= (int) $pid ?>&edit=1">Record dental history</a><?php endif; ?></div>
<?php else: ?>
<div class="grid-main-side"><section class="panel"><h2>Version <?= (int) $cur['version_no'] ?> <?= (int) $cur['version_no'] === (int) $latest['version_no'] ? badge('Current', 'ok') : badge('Earlier version', 'muted') ?></h2>
<p class="muted">Recorded <?= e(fmt_dt($cur['recorded_at'])) ?> by <?= e($cur['recorded_by_name']) ?></p>
<dl class="dl"><?= dl_row('Last dental visit', $cur['last_dental_visit_text']) ?><?= dl_row('Brushing frequency', $cur['brushing_frequency']) ?>
<?php foreach (clinic('dental_yesno') as $k => $lab): ?><?= dl_row($lab, label($cur[$k])) ?><?php if ($k === 'extraction_complications'): ?><?= dl_row('Extraction complication details', $cur['extraction_complication_note'], true) ?><?php endif; endforeach; ?>
<?= dl_row('Notes', $cur['additional_note'], true) ?></dl></section>
<aside class="panel"><h3>Versions</h3><ul class="plain versions"><?php foreach ($versions as $x): ?><li><a class="<?= (int) $x['version_no'] === (int) $cur['version_no'] ? 'on' : '' ?>" href="?patient_id=<?= (int) $pid ?>&version=<?= (int) $x['version_no'] ?>">Version <?= (int) $x['version_no'] ?></a><span class="muted"><?= e(fmt_date($x['recorded_at'])) ?> · <?= e($x['recorded_by_name']) ?></span></li><?php endforeach; ?></ul></aside></div>
<?php endif; ?>
<?php page_end();
