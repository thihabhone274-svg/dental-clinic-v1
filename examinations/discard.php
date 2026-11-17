<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login(); require_post();
$id = post_int('id'); $ex = exam_find($id);
if (!$ex) { abort(404, 'Not found', 'That examination does not exist.'); }
$visit = visit_find((int) $ex['visit_id']);
if (!can('exam.write') || !can_write_exam($visit)) { deny('Only the responsible doctor or an administrator can discard a draft.'); }
try { exam_discard($id, (string) ($_POST['reason_code'] ?? ''), (int) $u['user_id']); flash('success', 'Draft discarded.'); redirect('visits/view.php?id=' . (int) $ex['visit_id']); }
catch (DomainException $ex2) { flash('error', $ex2->getMessage()); }
redirect('examinations/view.php?id=' . $id);
