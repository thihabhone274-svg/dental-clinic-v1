<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login(true);
$err = [];
if (is_post()) {
    csrf_check();
    if (account_change_password((int) $u['user_id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''), $err)) {
        flash('success', 'Your password has been changed.');
        redirect('index.php');
    }
}
page_start('Change password', ['nav' => '', 'subtitle' => $u['must_change_password'] ? 'You must choose a new password before continuing.' : '']);
?>
<div class="panel narrow">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <?= f_text('current_password', 'Current password', [], $err, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <?= f_text('new_password', 'New password', [], $err, ['type' => 'password', 'required' => true, 'hint' => 'At least ' . (int) cfg('password_min_length') . ' characters.', 'autocomplete' => 'new-password']) ?>
    <?= f_text('confirm_password', 'Confirm new password', [], $err, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Change password</button></div>
  </form>
</div>
<?php page_end();
