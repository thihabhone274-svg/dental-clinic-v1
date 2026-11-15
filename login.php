<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) { redirect('index.php'); }
$err = []; $v = [];
if (is_post()) {
    csrf_check();
    $v['username'] = trim((string) ($_POST['username'] ?? ''));
    $pw = (string) ($_POST['password'] ?? '');
    if ($v['username'] === '' || $pw === '') { $err['form'] = 'Enter your username and password.'; }
    else { $msg = login_attempt($v['username'], $pw); if ($msg === null) { redirect(safe_intended()); } $err['form'] = $msg; }
}
page_start('Sign in');
?>
<div class="auth-card">
  <div class="auth-brand"><span class="brand-mark" aria-hidden="true"></span><span><?= e(cfg('app_name')) ?></span></div>
  <h1>Sign in</h1>
  <?php if (!empty($err['form'])): ?><div class="alert alert-error" role="alert"><?= e($err['form']) ?></div><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <?= f_text('username', 'Username', $v, [], ['required' => true, 'autocomplete' => 'username']) ?>
    <?= f_text('password', 'Password', [], [], ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
  </form>
  <p class="auth-foot">Need access? <a href="<?= e(url('request_account.php')) ?>">Request an account</a> (staff or doctors; an administrator must approve it).</p>
</div>
<?php page_end();
