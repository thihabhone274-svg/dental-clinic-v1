<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$id = is_post() ? post_int('id') : get_int('id'); $old = exam_find($id);
if (!$old) { abort(404, 'Not found', 'That examination does not exist.'); }
$visit = visit_find((int) $old['visit_id']);
if (!can('exam.write') || !can_write_exam($visit)) { deny('Only the responsible doctor or an administrator can correct examinations.'); }
if ($old['status'] !== 'FINALIZED' || $visit['status'] === 'CANCELLED') { flash('warn', 'Only a finalized examination that has not been corrected can be corrected.'); redirect('examinations/view.php?id=' . $id); }
$err = [];
$v = array_merge($old, ['examined_at' => exam_dt_input($old['examined_at'])], exam_extras($id));
if (is_post()) {
    require_post();
    $v = $_POST;
    [$c, $x] = exam_validate($_POST, $err);
    $reason = v_text($_POST, 'correction_reason', $err, 'Reason for correction', ['required' => true, 'max' => 500]);
    if (!$err) {
        try { $nid = exam_correct($id, $c, $x, (string) $reason, (int) $u['user_id']); flash('success', 'Correction saved. The original examination is kept and marked superseded.'); redirect('examinations/view.php?id=' . $nid); }
        catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    }
}
page_start('Correct examination #' . $id, ['nav' => 'visits', 'crumbs' => [['Visits', 'visits/index.php'], [$visit['visit_no'], 'visits/view.php?id=' . $visit['visit_id']], ['Examination #' . $id, 'examinations/view.php?id=' . $id]], 'subtitle' => $visit['patient_name'] . ' · ' . $visit['patient_code']]);
?><div class="alert alert-info">The original examination will be kept unchanged and marked <strong>Superseded</strong>. Your corrected version is added as a new finalized record linked to it.</div>
<?php if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was saved.</div><?php endif; ?>
<form method="post" novalidate class="panel exam-form" data-confirm="Save this correction? It becomes the finalized record, and the original is marked superseded."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
<?= f_textarea('correction_reason', 'Reason for correction', $v, $err, ['required' => true, 'rows' => 2, 'hint' => 'Required. Appears in the examination record.']) ?>
<?= exam_form_fields($v, $err, $visit) ?>
<div class="form-actions sticky"><button class="btn btn-primary" type="submit">Save correction</button> <a class="btn btn-ghost" href="<?= e(url('examinations/view.php?id=' . $id)) ?>">Cancel</a></div></form>
<?php page_end();
