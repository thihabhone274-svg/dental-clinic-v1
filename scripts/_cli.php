<?php
// Shared helpers for console scripts. These scripts refuse to run from a web server.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("This script can only be run from the command line.\n"); }
require_once __DIR__ . '/../includes/bootstrap.php';
function cli_line(string $label): string { echo $label; $l = fgets(STDIN); return $l === false ? '' : trim($l); }
function cli_secret(string $label): string {
    echo $label;
    if (!stream_isatty(STDIN)) { $l = fgets(STDIN); return $l === false ? '' : rtrim($l, "\r\n"); }   // piped input (automated installs)
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $cmd = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
        $out = shell_exec($cmd);
        if ($out !== null) { return rtrim($out, "\r\n"); }
        echo "\n(WARNING: could not hide the password; it will be visible.) "; return cli_line('');
    }
    shell_exec('stty -echo'); $l = fgets(STDIN); shell_exec('stty echo'); echo "\n";
    return $l === false ? '' : rtrim($l, "\r\n");
}
function cli_create_admin(): int {
    $username = cli_line('Administrator username (letters, digits . _ -): ');
    $name = cli_line('Display name: ');
    $pw = cli_secret('Password (min ' . cfg('password_min_length') . ' characters): ');
    $pw2 = cli_secret('Repeat password: ');
    $err = [];
    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) { $err[] = 'Username must be 3 to 80 characters: letters, digits, . _ -'; }
    if ($name === '' || mb_strlen($name) > 160) { $err[] = 'Display name is required (max 160 characters).'; }
    if ($e = password_error($pw, $username)) { $err[] = $e; } elseif ($pw !== $pw2) { $err[] = 'The passwords do not match.'; }
    if ($err) { fwrite(STDERR, implode("\n", $err) . "\nNothing was created.\n"); return 1; }
    try {
        tx(function () use ($username, $name, $pw) {
            q("SELECT role_code FROM role WHERE role_code = 'ADMIN' FOR UPDATE");
            if (active_admin_count() > 0) { throw new DomainException('An active administrator already exists.'); }
            if (q_val('SELECT 1 FROM user_account WHERE username_key = ?', [mb_strtolower($username)])) { throw new DomainException('That username is already used.'); }
            q('INSERT INTO user_account (username, username_key, display_name, password_hash) VALUES (?,?,?,?)', [$username, mb_strtolower($username), $name, password_hash($pw, PASSWORD_DEFAULT)]);
            $uid = (int) db()->lastInsertId();
            q("INSERT INTO user_role (user_id, role_code, assigned_by) VALUES (?, 'ADMIN', NULL)", [$uid]);
            audit('ACCOUNT_CREATED_BY_SETUP', 'user_account', $uid, 'SUCCESS', ['mode' => 'first_admin']);
        });
    } catch (DomainException $e) { fwrite(STDERR, $e->getMessage() . " Nothing was created.\n"); return 1; }
    echo "Administrator '$username' created. Sign in through the web page, then create further accounts from Accounts and roles.\n";
    return 0;
}
