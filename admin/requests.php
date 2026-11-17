<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_can('admin.requests');
if (is_post()) {
    require_post();
    $id = post_int('request_id');
    try {
        if (($_POST['action'] ?? '') === 'approve') {
            request_approve($id, array_filter([(string) ($_POST['role'] ?? '')]), (int) $u['user_id']);
            flash('success', 'Account created. The applicant can now sign in.');
        } elseif (($_POST['action'] ?? '') === 'reject') {
            $e = []; $reason = v_text($_POST, 'reason', $e, 'Reason', ['required' => true, 'max' => 500]);
            if ($e) { throw new DomainException($e['reason']); }
            request_reject($id, $reason, (int) $u['user_id']);
            flash('success', 'Request rejected.');
        }
    } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    redirect('admin/requests.php?status=' . urlencode((string) ($_GET['status'] ?? 'PENDING')));
}
$status = (string) ($_GET['status'] ?? 'PENDING'); if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) { $status = 'PENDING'; }
$rows = q_all('SELECT r.*, a.display_name AS reviewer FROM account_request r LEFT JOIN user_account a ON a.user_id = r.reviewed_by WHERE r.request_status = ? ORDER BY r.created_at DESC LIMIT 200', [$status]);
page_start('Account requests', ['nav' => 'requests', 'subtitle' => 'Staff and doctor applicants only. Administrator roles are granted on the Accounts and roles page.']);
?>
<div class="tabs"><?php foreach (['PENDING', 'APPROVED', 'REJECTED'] as $s): ?><a class="<?= $s === $status ? 'on' : '' ?>" href="?status=<?= e($s) ?>"><?= e(label($s)) ?></a><?php endforeach; ?></div>
<?php if (!$rows): ?><div class="empty-state"><h2>No <?= e(strtolower($status)) ?> requests</h2><p>There is nothing to show here.</p></div><?php else: ?>
<div class="stack"><?php foreach ($rows as $r): ?>
<article class="panel"><div class="row-between"><div><h3><?= e($r['applicant_name']) ?> <?= status_badge($r['request_status']) ?></h3>
  <p class="muted">Username <b><?= e($r['requested_username']) ?></b> · requested role <b><?= e(label($r['requested_role'])) ?></b> · submitted <?= e(fmt_dt($r['created_at'])) ?><?= $r['requested_email'] ? ' · ' . e($r['requested_email']) : '' ?><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></p>
  <?php if ($r['request_status'] !== 'PENDING'): ?><p class="muted">Decided by <?= e($r['reviewer'] ?? '') ?> on <?= e(fmt_dt($r['reviewed_at'])) ?><?= $r['rejection_reason'] ? '. Reason: ' . e($r['rejection_reason']) : '' ?></p><?php endif; ?></div></div>
<?php if ($r['request_status'] === 'PENDING'): ?>
  <div class="row-gap">
  <details class="drop"><summary class="btn btn-primary btn-sm">Approve…</summary><form method="post" class="drop-body" data-confirm="Create this account and assign the chosen role?"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
    <?= f_radio('role', 'Role to assign', ['STAFF' => 'Staff', 'DOCTOR' => 'Doctor'], ['role' => $r['requested_role']], []) ?><p class="hint">The administrator role cannot be assigned here.</p><button class="btn btn-primary btn-sm">Approve and create account</button></form></details>
  <details class="drop"><summary class="btn btn-danger btn-sm">Reject…</summary><form method="post" class="drop-body" data-confirm="Reject this request? The applicant will not be able to sign in."><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
    <?= f_textarea('reason', 'Reason (required)', [], [], ['required' => true, 'rows' => 2]) ?><button class="btn btn-danger btn-sm">Reject request</button></form></details></div>
<?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
<?php page_end();
