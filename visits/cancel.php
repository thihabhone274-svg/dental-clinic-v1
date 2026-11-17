<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login(); require_post(); require_can('visit.close');
$id = post_int('id'); $v = visit_find($id);
if (!$v) { abort(404, 'Not found', 'That visit does not exist.'); }
if (!can_close_visit($v)) { deny('Only the responsible doctor, staff or an administrator can cancel this visit.'); }
try { visit_cancel($id, (string) ($_POST['reason_code'] ?? ''), (int) $u['user_id']); flash('success', 'Visit cancelled.'); } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
redirect('visits/view.php?id=' . $id);
