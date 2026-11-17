<?php
declare(strict_types=1);
function account_request_submit(array $in, array &$err): bool {
    $name = v_text($in, 'applicant_name', $err, 'Full name', ['required' => true, 'max' => 160]);
    $user = v_text($in, 'username', $err, 'Username', ['required' => true, 'min' => 3, 'max' => 80, 'pattern' => '/^[A-Za-z0-9._-]+$/', 'pattern_msg' => 'Username may contain letters, digits and . _ - only.']);
    $email = v_text($in, 'email', $err, 'Email', ['max' => 190]);
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $err['email'] = 'Enter a valid email address, or leave it blank.'; }
    $phone = v_phone($in, 'phone', $err, 'Telephone number');
    $role = (string) ($in['requested_role'] ?? '');
    if (!in_array($role, ['STAFF', 'DOCTOR'], true)) {   // the ONLY roles a public request may ask for; anything else is rejected and audited
        if ($role !== '') { audit('ACCESS_DENIED', 'account_request', null, 'DENIED', ['mode' => 'invalid_role_requested']); }
        $err['requested_role'] = 'Choose Staff or Doctor.';
    }
    $pw = (string) ($in['password'] ?? '');
    if ($pe = password_error($pw, $user)) { $err['password'] = $pe; } elseif ($pw !== (string) ($in['password_confirm'] ?? '')) { $err['password_confirm'] = 'The passwords do not match.'; }
    if ($err) { return false; }
    $uk = mb_strtolower($user); $ek = $email !== null ? mb_strtolower($email) : null;
    try {
        tx(function () use ($name, $user, $uk, $email, $ek, $phone, $role, $pw) {
            if (q_val('SELECT 1 FROM user_account WHERE username_key = ?', [$uk]) || ($ek && q_val('SELECT 1 FROM user_account WHERE email_key = ?', [$ek]))) { throw new DomainException('unavailable'); }
            q('INSERT INTO account_request (applicant_name, requested_username, requested_username_key, requested_email, requested_email_key, phone, requested_role, password_hash, pending_username_key, pending_email_key)
               VALUES (?,?,?,?,?,?,?,?,?,?)', [$name, $user, $uk, $email, $ek, $phone, $role, password_hash($pw, PASSWORD_DEFAULT), $uk, $ek]);
            audit('ACCOUNT_REQUEST_SUBMITTED', 'account_request', (int) db()->lastInsertId());
        });
    } catch (DomainException | PDOException $e) {
        if ($e instanceof PDOException && ($e->errorInfo[1] ?? 0) !== 1062) { throw $e; }
        $err['username'] = 'That username or email is not available.';
        return false;
    }
    return true;
}
function doctor_options(): array {
    $o = [];
    foreach (q_all("SELECT d.doctor_id, d.full_name FROM doctor d JOIN user_account u ON u.user_id = d.user_id WHERE d.profile_status = 'ACTIVE' AND u.account_status = 'ACTIVE'
                    AND EXISTS (SELECT 1 FROM user_role r WHERE r.user_id = d.user_id AND r.role_code = 'DOCTOR' AND r.active_flag = 1) ORDER BY d.full_name") as $r) { $o[$r['doctor_id']] = 'Dr. ' . preg_replace('/^Dr\.?\s+/i', '', $r['full_name']); }
    return $o;
}
function doctor_ensure_profile(int $userId, string $fullName, ?string $license = null): void {
    $d = q_one('SELECT doctor_id FROM doctor WHERE user_id = ?', [$userId]);
    if ($d) { q("UPDATE doctor SET profile_status = 'ACTIVE' WHERE doctor_id = ?", [$d['doctor_id']]); return; }
    q('INSERT INTO doctor (user_id, doctor_code, full_name, license_number) VALUES (?,?,?,?)', [$userId, 'TMP' . bin2hex(random_bytes(6)), $fullName, $license]);
    $id = (int) db()->lastInsertId();
    q('UPDATE doctor SET doctor_code = ? WHERE doctor_id = ?', [next_code('DR', $id, 4), $id]);
}
function account_roles(int $userId): array { return array_column(q_all('SELECT role_code FROM user_role WHERE user_id = ? AND active_flag = 1 ORDER BY role_code', [$userId]), 'role_code'); }
function active_admin_count(): int {
    return (int) q_val("SELECT COUNT(DISTINCT u.user_id) FROM user_account u JOIN user_role r ON r.user_id = u.user_id AND r.role_code = 'ADMIN' AND r.active_flag = 1 WHERE u.account_status = 'ACTIVE'");
}
function request_approve(int $id, array $roles, int $adminId): int {
    return tx(function () use ($id, $roles, $adminId) {
        $r = q_one('SELECT * FROM account_request WHERE request_id = ? FOR UPDATE', [$id]);
        if (!$r) { throw new DomainException('Request not found.'); }
        if ($r['request_status'] !== 'PENDING') { throw new DomainException('This request has already been decided.'); }
        foreach ($roles as $x) { if (!in_array($x, ['STAFF', 'DOCTOR'], true)) { audit('ACCESS_DENIED', 'account_request', $id, 'DENIED', ['mode' => 'admin_role_in_approval']); throw new DomainException('Only STAFF or DOCTOR can be assigned here. The administrator role is granted only on the Accounts and roles page.'); } }
        if ($e = validate_roles($roles)) { throw new DomainException($e); }
        if (q_val('SELECT 1 FROM user_account WHERE username_key = ? OR (? IS NOT NULL AND email_key = ?)', [$r['requested_username_key'], $r['requested_email_key'], $r['requested_email_key']])) { throw new DomainException('That username or email is now in use by an existing account.'); }
        q('INSERT INTO user_account (username, username_key, display_name, email, email_key, phone, password_hash) VALUES (?,?,?,?,?,?,?)',
          [$r['requested_username'], $r['requested_username_key'], $r['applicant_name'], $r['requested_email'], $r['requested_email_key'], $r['phone'], $r['password_hash']]);
        $uid = (int) db()->lastInsertId();
        foreach ($roles as $role) { q('INSERT INTO user_role (user_id, role_code, assigned_by) VALUES (?,?,?)', [$uid, $role, $adminId]); }
        if (in_array('DOCTOR', $roles, true)) { doctor_ensure_profile($uid, $r['applicant_name']); }
        q("UPDATE account_request SET request_status = 'APPROVED', reviewed_by = ?, reviewed_at = NOW(), created_user_id = ?, password_hash = NULL, pending_username_key = NULL, pending_email_key = NULL WHERE request_id = ?", [$adminId, $uid, $id]);
        audit('ACCOUNT_REQUEST_APPROVED', 'account_request', $id, 'SUCCESS', ['roles' => $roles, 'target_user' => $uid]);
        return $uid;
    });
}
function request_reject(int $id, string $reason, int $adminId): void {
    tx(function () use ($id, $reason, $adminId) {
        $r = q_one('SELECT request_status FROM account_request WHERE request_id = ? FOR UPDATE', [$id]);
        if (!$r) { throw new DomainException('Request not found.'); }
        if ($r['request_status'] !== 'PENDING') { throw new DomainException('This request has already been decided.'); }
        q("UPDATE account_request SET request_status = 'REJECTED', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ?, password_hash = NULL, pending_username_key = NULL, pending_email_key = NULL WHERE request_id = ?", [$adminId, $reason, $id]);
        audit('ACCOUNT_REQUEST_REJECTED', 'account_request', $id);
    });
}
function role_grant(int $userId, string $role, int $adminId, string $adminPassword): void {
    tx(function () use ($userId, $role, $adminId, $adminPassword) {
        $u = q_one('SELECT user_id, display_name, account_status FROM user_account WHERE user_id = ? FOR UPDATE', [$userId]);
        if (!$u || $u['account_status'] !== 'ACTIVE') { throw new DomainException('Choose an active account.'); }
        $have = account_roles($userId);
        if (in_array($role, $have, true)) { throw new DomainException('The account already has that role.'); }
        if ($e = validate_roles(array_merge($have, [$role]))) { throw new DomainException($e); }
        if ($role === 'ADMIN') {   // extra confirmation: the acting administrator re-enters their own password
            $h = (string) q_val('SELECT password_hash FROM user_account WHERE user_id = ?', [$adminId]);
            if (!password_verify($adminPassword, $h)) { audit('ROLE_ASSIGNED', 'user_account', $userId, 'DENIED', ['role' => 'ADMIN', 'mode' => 'password_check_failed']); throw new DomainException('Your password was not correct. The administrator role was not granted.'); }
        }
        q('INSERT INTO user_role (user_id, role_code, assigned_by) VALUES (?,?,?)', [$userId, $role, $adminId]);
        if ($role === 'DOCTOR') { doctor_ensure_profile($userId, $u['display_name']); }
        audit('ROLE_ASSIGNED', 'user_account', $userId, 'SUCCESS', ['role' => $role]);
    });
}
function role_revoke(int $userId, string $role, int $adminId): void {
    tx(function () use ($userId, $role, $adminId) {
        q('SELECT user_id FROM user_account WHERE user_id = ? FOR UPDATE', [$userId]);
        if (!in_array($role, account_roles($userId), true)) { throw new DomainException('The account does not have that role.'); }
        if ($role === 'ADMIN' && ($userId === $adminId || active_admin_count() <= 1)) { throw new DomainException('You cannot remove your own administrator role or the last administrator.'); }
        if (count(account_roles($userId)) <= 1) { throw new DomainException('An account must keep at least one role. Disable the account instead.'); }
        q('UPDATE user_role SET active_flag = NULL, revoked_by = ?, revoked_at = NOW() WHERE user_id = ? AND role_code = ? AND active_flag = 1', [$adminId, $userId, $role]);
        if ($role === 'DOCTOR') { q("UPDATE doctor SET profile_status = 'INACTIVE' WHERE user_id = ?", [$userId]); }
        audit('ROLE_REVOKED', 'user_account', $userId, 'SUCCESS', ['role' => $role]);
    });
}
function account_set_active(int $userId, bool $active, int $adminId): void {
    tx(function () use ($userId, $active, $adminId) {
        $u = q_one('SELECT account_status FROM user_account WHERE user_id = ? FOR UPDATE', [$userId]);
        if (!$u) { throw new DomainException('Account not found.'); }
        if (!$active && ($userId === $adminId)) { throw new DomainException('You cannot deactivate your own account.'); }
        if (!$active && in_array('ADMIN', account_roles($userId), true) && active_admin_count() <= 1) { throw new DomainException('You cannot deactivate the last administrator.'); }
        q('UPDATE user_account SET account_status = ? WHERE user_id = ?', [$active ? 'ACTIVE' : 'DISABLED', $userId]);
        audit($active ? 'ACCOUNT_ENABLED' : 'ACCOUNT_DISABLED', 'user_account', $userId);
    });
}
/** Administrator-assisted reset: sets a one-time temporary password and forces a change at next login. */
function account_reset_password(int $userId, int $adminId): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'; $tmp = '';
    for ($i = 0; $i < 12; $i++) { $tmp .= $chars[random_int(0, strlen($chars) - 1)]; }
    tx(function () use ($userId, $adminId, $tmp) {
        if (!q_val('SELECT 1 FROM user_account WHERE user_id = ? FOR UPDATE', [$userId])) { throw new DomainException('Account not found.'); }
        q('UPDATE user_account SET password_hash = ?, must_change_password = 1 WHERE user_id = ?', [password_hash($tmp, PASSWORD_DEFAULT), $userId]);
        audit('PASSWORD_RESET_BY_ADMIN', 'user_account', $userId);
    });
    return $tmp;
}
function account_change_password(int $userId, string $current, string $new, string $confirm, array &$err): bool {
    $row = q_one('SELECT username, password_hash FROM user_account WHERE user_id = ?', [$userId]);
    if (!$row || !password_verify($current, $row['password_hash'])) { $err['current_password'] = 'Your current password is not correct.'; }
    if ($pe = password_error($new, $row['username'] ?? null)) { $err['new_password'] = $pe; }
    elseif ($new === $current) { $err['new_password'] = 'Choose a password different from the current one.'; }
    elseif ($new !== $confirm) { $err['confirm_password'] = 'The passwords do not match.'; }
    if ($err) { audit('PASSWORD_CHANGED', 'user_account', $userId, 'FAILED'); return false; }
    tx(function () use ($userId, $new) { q('UPDATE user_account SET password_hash = ?, must_change_password = 0 WHERE user_id = ?', [password_hash($new, PASSWORD_DEFAULT), $userId]); audit('PASSWORD_CHANGED', 'user_account', $userId); });
    return true;
}
