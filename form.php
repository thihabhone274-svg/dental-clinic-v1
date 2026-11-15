<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
$eid = is_post() ? post_int('id') : get_int('id'); $vid = is_post() ? post_int('visit_id') : get_int('visit_id');
$exam = null;
if ($eid) { $exam = exam_find($eid); if (!$exam) { abort(404, 'Not found', 'That examination does not exist.'); } $vid = (int) $exam['visit_id']; }
$visit = $vid ? visit_find($vid) : null;
if (!$visit) { abort(404, 'Not found', 'That visit does not exist.'); }
if (!can('exam.write') || !can_write_exam($visit)) { deny('Only the responsible doctor or an administrator can enter examinations.'); }
if ($visit['status'] !== 'OPEN') { flash('warn', 'Examinations can be started or edited only while the visit is open.'); redirect('visits/view.php?id=' . $vid); }
if ($exam && $exam['status'] !== 'DRAFT') { flash('warn', 'Only a draft can be edited. Use “Correct examination” for a finalized one.'); redirect('examinations/view.php?id=' . $eid); }
if (!$exam && ($cur = exam_current($vid))) { flash('info', 'This visit already has a current examination.'); redirect('examinations/view.php?id=' . $cur['exam_id']); }
$err = [];
$v = $exam ? array_merge($exam, ['examined_at' => exam_dt_input($exam['examined_at'])], exam_extras((int) $exam['exam_id'])) : ['examined_at' => date('Y-m-d\TH:i')];
if (is_post()) {
    require_post();
    $v = $_POST;
    [$c, $x] = exam_validate($_POST, $err);
    if (!$err) {
        try {
            $nid = exam_save_draft($vid, $eid ?: null, $c, $x, (int) $u['user_id']);
            flash('success', 'Draft saved. Review it, then finalize when it is complete.');
            redirect(isset($_POST['continue']) ? 'examinations/form.php?id=' . $nid : 'examinations/view.php?id=' . $nid);
        } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    }
}
page_start($exam ? 'Edit draft examination' : 'New examination', ['nav' => 'visits', 'crumbs' => [['Visits', 'visits/index.php'], [$visit['visit_no'], 'visits/view.php?id=' . $vid]], 'subtitle' => $visit['patient_name'] . ' · ' . $visit['patient_code'], 'actions' => badge('Draft', 'warn')]);
if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was saved. Required fields are marked *.</div><?php endif; ?>
<form method="post" novalidate class="panel exam-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $eid ?>"><input type="hidden" name="visit_id" value="<?= (int) $vid ?>">
<?= exam_form_fields($v, $err, $visit) ?>
<div class="form-actions sticky"><button class="btn btn-primary" type="submit">Save draft and review</button> <button class="btn btn-secondary" type="submit" name="continue" value="1">Save and keep editing</button> <a class="btn btn-ghost" href="<?= e(url('visits/view.php?id=' . $vid)) ?>">Cancel</a></div></form>
<?php page_end();
