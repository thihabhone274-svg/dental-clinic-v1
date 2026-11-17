<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_can('admin.accounts');
if (is_post()) {
    require_post();
    $uid = post_int('user_id'); $act = (string) ($_POST['action'] ?? '');
    try {
        if ($act === 'grant') { role_grant($uid, (string) ($_POST['role'] ?? ''), (int) $u['user_id'], (string) ($_POST['admin_password'] ?? '')); flash('success', 'Role granted.'); }
        elseif ($act === 'revoke') { role_revoke($uid, (string) ($_POST['role'] ?? ''), (int) $u['user_id']); flash('success', 'Role removed.'); }
        elseif ($act === 'disable' || $act === 'enable') { account_set_active($uid, $act === 'enable', (int) $u['user_id']); flash('success', $act === 'enable' ? 'Account activated.' : 'Account deactivated. The user is signed out on their next request.'); }
        elseif ($act === 'reset') {
            $name = (string) q_val('SELECT display_name FROM user_account WHERE user_id = ?', [$uid]);
            $tmp = account_reset_password($uid, (int) $u['user_id']);
            flash('success', "Temporary password for $name: $tmp. Give it to them in person; it is shown only once and they must change it at next sign-in.");
        }
    } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
    redirect('admin/accounts.php');
}
$accounts = q_all('SELECT user_id, username, display_name, account_status, last_login_at, must_change_password FROM user_account ORDER BY display_name');
page_start('Accounts and roles', ['nav' => 'accounts', 'subtitle' => 'Roles: Staff, Doctor, Administrator. One account cannot be both Staff and Doctor.']);
?>
<p><a class="btn btn-primary" href="<?= e(url('admin/create_account.php')) ?>">Create staff or doctor account</a></p>
<div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Username</th><th>Roles</th><th>Status</th><th>Last sign-in</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($accounts as $a): $roles = account_roles((int) $a['user_id']); $self = (int) $a['user_id'] === (int) $u['user_id']; ?>
<tr><td><?= e($a['display_name']) ?><?= $self ? ' <span class="muted">(you)</span>' : '' ?></td><td><?= e($a['username']) ?></td>
<td><?php foreach ($roles as $r): ?><?= badge(label($r), $r === 'ADMIN' ? 'ok' : 'info') ?> <?= post_button('admin/accounts.php', ['action' => 'revoke', 'user_id' => $a['user_id'], 'role' => $r], 'Remove ' . label($r), 'btn-ghost', 'Remove the ' . label($r) . ' role from ' . $a['display_name'] . '?') ?><br><?php endforeach; ?></td>
<td><?= status_badge($a['account_status']) ?><?= $a['must_change_password'] ? ' ' . badge('Must change password', 'warn') : '' ?></td><td><?= e(fmt_dt($a['last_login_at'])) ?></td>
<td><div class="row-gap">
<details class="drop"><summary class="btn btn-secondary btn-sm">Grant role…</summary><form method="post" class="drop-body" data-confirm="Grant this role?"><?= csrf_field() ?><input type="hidden" name="action" value="grant"><input type="hidden" name="user_id" value="<?= (int) $a['user_id'] ?>">
 <?= f_select('role', 'Role', ['STAFF' => 'Staff', 'DOCTOR' => 'Doctor', 'ADMIN' => 'Administrator'], [], []) ?>
 <?= f_text('admin_password', 'Your password (required for Administrator)', [], [], ['type' => 'password', 'autocomplete' => 'current-password']) ?><button class="btn btn-primary btn-sm">Grant</button></form></details>
<?= $a['account_status'] === 'ACTIVE' ? post_button('admin/accounts.php', ['action' => 'disable', 'user_id' => $a['user_id']], 'Deactivate', 'btn-danger', 'Deactivate ' . $a['display_name'] . '? They will be signed out.') : post_button('admin/accounts.php', ['action' => 'enable', 'user_id' => $a['user_id']], 'Activate', 'btn-secondary', 'Activate this account?') ?>
<?= post_button('admin/accounts.php', ['action' => 'reset', 'user_id' => $a['user_id']], 'Reset password', 'btn-secondary', 'Set a temporary password for ' . $a['display_name'] . '? Their current password will stop working.') ?>
</div></td></tr><?php endforeach; ?></tbody></table></div>
<?php page_end();
