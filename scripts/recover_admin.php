<?php
// Emergency recovery when NO administrator can sign in. Requires access to this computer.
// Run:   C:\xampp\php\php.exe scripts\recover_admin.php
// It lists administrator accounts, lets you set a new password and re-activate one (or create a new one if none exists),
// and records the event in the audit log (without any password).
require __DIR__ . '/_cli.php';
if (!stream_isatty(STDIN) && getenv('DENTAL_ALLOW_NONINTERACTIVE') !== '1') { fwrite(STDERR, "Recovery must be run interactively.\n"); exit(1); }
$admins = q_all("SELECT DISTINCT u.user_id, u.username, u.account_status FROM user_account u JOIN user_role r ON r.user_id = u.user_id AND r.role_code = 'ADMIN' AND r.active_flag = 1 ORDER BY u.username");
echo "Administrator accounts:\n"; foreach ($admins as $a) { echo "  - {$a['username']} ({$a['account_status']})\n"; } if (!$admins) { echo "  (none)\n"; }
if (cli_line("Type RECOVER ADMIN to continue: ") !== 'RECOVER ADMIN') { echo "Cancelled. Nothing was changed.\n"; exit(1); }
if (!$admins) { echo "No administrator exists. Creating a new one.\n"; exit(cli_create_admin()); }
$name = cli_line('Username of the administrator to recover: ');
$row = null; foreach ($admins as $a) { if (mb_strtolower($a['username']) === mb_strtolower($name)) { $row = $a; } }
if (!$row) { fwrite(STDERR, "That is not an administrator account. Nothing was changed.\n"); exit(1); }
$pw = cli_secret('New password: '); $pw2 = cli_secret('Repeat new password: ');
if ($e = password_error($pw, $row['username'])) { fwrite(STDERR, "$e Nothing was changed.\n"); exit(1); }
if ($pw !== $pw2) { fwrite(STDERR, "The passwords do not match. Nothing was changed.\n"); exit(1); }
tx(function () use ($row, $pw) {
    q("UPDATE user_account SET password_hash = ?, account_status = 'ACTIVE', must_change_password = 0 WHERE user_id = ?", [password_hash($pw, PASSWORD_DEFAULT), $row['user_id']]);
    audit('ADMIN_RECOVERED_BY_CONSOLE', 'user_account', (int) $row['user_id'], 'SUCCESS', ['mode' => 'password_reset']);
});
echo "Done. '{$row['username']}' is active and can sign in with the new password.\n";
