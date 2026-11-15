<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) { redirect('index.php'); }
$err = []; $v = [];
if (is_post()) {
    csrf_check();
    $v = $_POST;
    if (account_request_submit($_POST, $err)) {
        flash('success', 'Request received. An administrator must approve it before you can sign in.');
        redirect('login.php');
    }
}
page_start('Request an account');
?>
<div class="auth-card wide">
  <div class="auth-brand"><span class="brand-mark" aria-hidden="true"></span><span><?= e(cfg('app_name')) ?></span></div>
  <h1>Request an account</h1>
  <p class="sub">For clinic staff and doctors. You cannot sign in until an administrator approves your request. Administrator accounts are never created from this form.</p>
  <?php if ($err): ?><div class="alert alert-error" role="alert">Please correct the highlighted fields. Nothing was submitted.</div><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="grid-2">
      <?= f_text('applicant_name', 'Full name', $v, $err, ['required' => true, 'maxlength' => 160]) ?>
      <?= f_text('username', 'Username', $v, $err, ['required' => true, 'maxlength' => 80, 'hint' => 'Letters, digits, dot, underscore, hyphen.']) ?>
      <?= f_text('email', 'Email (optional)', $v, $err, ['maxlength' => 190, 'type' => 'email']) ?>
      <?= f_text('phone', 'Telephone (optional)', $v, $err, ['maxlength' => 40]) ?>
      <?= f_select('requested_role', 'Requested role', ['STAFF' => 'Staff', 'DOCTOR' => 'Doctor'], $v, $err, ['required' => true, 'placeholder' => 'Choose a role']) ?>
      <div></div>
      <?= f_text('password', 'Password', [], $err, ['type' => 'password', 'required' => true, 'hint' => 'At least ' . (int) cfg('password_min_length') . ' characters.', 'autocomplete' => 'new-password']) ?>
      <?= f_text('password_confirm', 'Confirm password', [], $err, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Submit request</button> <a class="btn btn-secondary" href="<?= e(url('login.php')) ?>">Back to sign in</a></div>
  </form>
</div>
<?php page_end();
