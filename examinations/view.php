<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_can('exam.view');
$id = get_int('id'); $ex = exam_find($id);
if (!$ex) { abort(404, 'Not found', 'That examination does not exist.'); }
$visit = visit_find((int) $ex['visit_id']);
if (!can_access_visit($visit)) { deny('You can view only examinations from your own visits.'); }
$x = exam_extras($id);
$canWrite = can('exam.write') && can_write_exam($visit);
$successor = q_one('SELECT exam_id FROM clinical_examination WHERE supersedes_exam_id = ?', [$id]);
page_start('Examination #' . $id, ['nav' => 'visits', 'crumbs' => [['Visits', 'visits/index.php'], [$visit['visit_no'], 'visits/view.php?id=' . $visit['visit_id']]], 'subtitle' => $visit['patient_name'] . ' · ' . $visit['patient_code'], 'actions' => status_badge($ex['status'])]);
if ($ex['status'] === 'SUPERSEDED'): ?><div class="alert alert-info">This examination was corrected. It is kept unchanged for the record. <?php if ($successor): ?><a href="<?= e(url('examinations/view.php?id=' . $successor['exam_id'])) ?>">View the corrected version</a>.<?php endif; ?></div><?php endif;
if ($ex['status'] === 'DISCARDED'): ?><div class="alert alert-info">This draft was discarded (<?= e(clinic('discard_reasons')[$ex['discard_reason_code']] ?? '') ?>). It is kept for the record.</div><?php endif;
if ($ex['status'] === 'DRAFT'): ?><div class="alert alert-warn">This is a <strong>draft</strong>. It is not part of the finalized record until it is finalized.</div><?php endif; ?>
<div class="grid-main-side"><section class="panel"><?= exam_display($ex, $x) ?></section>
<aside class="stack"><section class="panel"><h3>Record</h3><dl class="dl">
<?= dl_row('Status', label($ex['status'])) ?><?= dl_row('Examined', fmt_dt($ex['examined_at'])) ?><?= dl_row('Responsible doctor', $visit['doctor_name']) ?><?= dl_row('Entered by', $ex['author_name']) ?><?= dl_row('Visit', $visit['visit_no']) ?>
<?= dl_row('Created', fmt_dt($ex['created_at'])) ?><?= dl_row('Last updated', fmt_dt($ex['updated_at'])) ?>
<?php if ($ex['finalized_at']): ?><?= dl_row('Finalized', fmt_dt($ex['finalized_at'])) ?><?php endif; ?>
<?php if ($ex['supersedes_exam_id']): ?><div class="dl-row"><dt>Correction of</dt><dd><a href="<?= e(url('examinations/view.php?id=' . $ex['supersedes_exam_id'])) ?>">Examination #<?= (int) $ex['supersedes_exam_id'] ?></a></dd></div><?= dl_row('Reason for correction', $ex['correction_reason'], true) ?><?php endif; ?></dl>
<p class="hint">Finalizing records who finalized and when. It is not a legally binding digital signature.</p></section>
<?php if ($canWrite): ?><section class="panel"><h3>Actions</h3>
<?php if ($ex['status'] === 'DRAFT' && $visit['status'] === 'OPEN'): ?>
  <a class="btn btn-secondary" href="<?= e(url('examinations/form.php?id=' . $id)) ?>">Edit draft</a>
  <?= post_button('examinations/finalize.php', ['id' => $id], 'Finalize examination', 'btn-primary', 'Finalize this examination? A finalized examination cannot be edited. Later changes are saved as a correction and the original is kept.') ?>
  <details class="drop"><summary class="btn btn-danger btn-sm">Discard draft…</summary><form method="post" action="<?= e(url('examinations/discard.php')) ?>" class="drop-body" data-confirm="Discard this draft? It is kept for the record but marked discarded."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
   <?= f_select('reason_code', 'Reason', clinic('discard_reasons'), [], [], ['required' => true, 'placeholder' => 'Choose a reason']) ?><button class="btn btn-danger btn-sm">Discard draft</button></form></details>
<?php elseif ($ex['status'] === 'FINALIZED' && !$successor && $visit['status'] !== 'CANCELLED'): ?>
  <a class="btn btn-secondary" href="<?= e(url('examinations/correct.php?id=' . $id)) ?>">Correct examination</a><p class="hint">A correction adds a new finalized record with your reason. The original is kept.</p>
<?php else: ?><p class="muted">No actions available.</p><?php endif; ?></section><?php endif; ?></aside></div>
<?php page_end();
