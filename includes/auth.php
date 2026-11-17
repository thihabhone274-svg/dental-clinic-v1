<?php
declare(strict_types=1);
// ALL role decisions live here. Pages call can()/require_can()/can_access_*() and never compare role names themselves.
const ALL_ROLES = ['ADMIN', 'STAFF', 'DOCTOR'];
const PERMISSIONS = [
    'patient.view'    => ['ADMIN', 'STAFF', 'DOCTOR'],   // doctors are further limited by can_access_patient()
    'patient.write'   => ['ADMIN', 'STAFF'],
    'patient.archive' => ['ADMIN', 'STAFF'],
    'history.view'    => ['ADMIN', 'STAFF', 'DOCTOR'],
    'history.write'   => ['ADMIN', 'STAFF'],             // DOCTOR is deliberately absent: histories are read-only for doctors
    'appointment.view'  => ['ADMIN', 'STAFF', 'DOCTOR'],
    'appointment.write' => ['ADMIN', 'STAFF'],
    'visit.view'  => ['ADMIN', 'STAFF', 'DOCTOR'],
    'visit.open'  => ['ADMIN', 'STAFF', 'DOCTOR'],
    'visit.close' => ['ADMIN', 'STAFF', 'DOCTOR'],
    'exam.view'   => ['ADMIN', 'STAFF', 'DOCTOR'],
    'exam.write'  => ['ADMIN', 'DOCTOR'],                // draft, finalize, discard, correct (doctors only for their own visits)
    'admin.requests' => ['ADMIN'],
    'admin.accounts' => ['ADMIN'],
    'admin.audit'    => ['ADMIN'],
];

function current_user(bool $reload = false): ?array {
    static $user = null, $loaded = false;
    if ($reload) { $loaded = false; }
    if ($loaded) { return $user; }
    $loaded = true; $user = null;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) { return null; }
    $row = q_one('SELECT user_id, username, display_name, account_status, must_change_password FROM user_account WHERE user_id = ?', [$id]);
    $roles = $row ? array_column(q_all('SELECT role_code FROM user_role WHERE user_id = ? AND active_flag = 1', [$id]), 'role_code') : [];
    if (!$row || $row['account_status'] !== 'ACTIVE' || !$roles) { unset($_SESSION['user_id']); return null; }
    $d = q_one("SELECT doctor_id FROM doctor WHERE user_id = ? AND profile_status = 'ACTIVE'", [$id]);
    $user = $row + ['roles' => $roles, 'doctor_id' => $d ? (int) $d['doctor_id'] : null];
    return $user;
}
function primary_role(array $u): string { return in_array('ADMIN', $u['roles'], true) ? 'ADMIN' : (string) $u['roles'][0]; }
function has_role(string $role): bool { $u = current_user(); return $u !== null && in_array($role, $u['roles'], true); }
function is_admin(): bool { return has_role('ADMIN'); }
function can(string $perm): bool {
    $u = current_user();
    return $u !== null && isset(PERMISSIONS[$perm]) && array_intersect(PERMISSIONS[$perm], $u['roles']) !== [];
}
function safe_intended(): string {
    $p = (string) ($_SESSION['intended'] ?? ''); unset($_SESSION['intended']);
    $base = base_path();
    return ($p !== '' && $p[0] === '/' && !str_starts_with($p, '//') && ($base === '' || str_starts_with($p, $base . '/'))) ? substr($p, strlen($base)) : 'index.php';
}
function require_login(bool $allowPasswordChange = false): array {
    $u = current_user();
    if (!$u) {
        if (!is_post()) { $_SESSION['intended'] = (string) ($_SERVER['REQUEST_URI'] ?? ''); }
        redirect('login.php');
    }
    if ($u['must_change_password'] && !$allowPasswordChange) { redirect('auth/change_password.php'); }
    return $u;
}
function deny(string $why = 'You do not have permission to do this.'): never {
    audit('ACCESS_DENIED', substr(preg_replace('/[^A-Za-z0-9_.\-]/', '', basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'cli'))), 0, 40), null, 'DENIED');
    abort(403, 'Access denied', $why);
}
function require_can(string $perm): array { $u = require_login(); if (!can($perm)) { deny(); } return $u; }

// ---- object-level scope (the server enforces these; hiding buttons is not enough) ----
function doctor_in_active_care(int $patientId, int $doctorId): bool {
    return (bool) q_val("SELECT 1 FROM appointment WHERE patient_id = ? AND doctor_id = ? AND status IN ('SCHEDULED','CONFIRMED') AND DATE(start_at) >= CURDATE() LIMIT 1", [$patientId, $doctorId])
        || (bool) q_val("SELECT 1 FROM clinical_visit WHERE patient_id = ? AND doctor_id = ? AND status IN ('OPEN','CLOSED') LIMIT 1", [$patientId, $doctorId]);
}
function can_access_patient(int $patientId): bool {
    $u = current_user();
    if (!$u) { return false; }
    if (has_role('ADMIN') || has_role('STAFF')) { return true; }
    if (has_role('DOCTOR')) {
        return cfg('doctor_scope') === 'all' || ($u['doctor_id'] !== null && doctor_in_active_care($patientId, $u['doctor_id']));
    }
    return false;
}
function is_own_visit(array $visit): bool { $u = current_user(); return $u && $u['doctor_id'] !== null && (int) $visit['doctor_id'] === $u['doctor_id']; }
function can_access_visit(array $visit): bool {
    if (has_role('ADMIN') || has_role('STAFF')) { return true; }
    return has_role('DOCTOR') && (cfg('doctor_scope') === 'all' || is_own_visit($visit));
}
function can_write_exam(array $visit): bool { return can('exam.write') && is_own_visit($visit); }
function can_close_visit(array $visit): bool { return has_role('STAFF') || has_role('ADMIN') || is_own_visit($visit); }
function require_patient_access(int $id): array {
    require_can('patient.view');
    $p = patient_find($id);
    if (!$p) { abort(404, 'Not found', 'That patient record does not exist.'); }
    if (!can_access_patient($id)) { deny('This patient is not in your active care.'); }
    return $p;
}

// ---- accounts, roles, passwords ----
function validate_roles(array $roles): ?string {
    $roles = array_values(array_unique($roles));
    if (!$roles) { return 'Choose at least one role.'; }
    foreach ($roles as $r) { if (!in_array($r, ALL_ROLES, true)) { return 'Unknown role.'; } }
    if (in_array('STAFF', $roles, true) && in_array('DOCTOR', $roles, true)) {
        return 'One account cannot be both STAFF and DOCTOR (that would let a doctor edit histories through the staff role). Create two accounts, or use ADMIN.';
    }
    return null;
}
function password_error(string $pw, ?string $username = null): ?string {
    $min = (int) cfg('password_min_length', 8);
    if (mb_strlen($pw) < $min) { return "Password must be at least $min characters."; }
    if (strlen($pw) > 72) { return 'Password must be at most 72 bytes.'; }
    if ($username !== null && mb_strtolower($pw) === mb_strtolower($username)) { return 'Password must not equal the username.'; }
    return null;
}
function login_attempt(string $username, string $password): ?string {
    $recent = (int) q_val("SELECT COUNT(*) FROM audit_event WHERE action_code = 'LOGIN_FAILED' AND ip_address = ? AND occurred_at > (NOW() - INTERVAL 5 MINUTE)", [client_ip()]);
    if ($recent >= (int) cfg('login_max_failures_per_5min', 10)) { return 'Too many failed attempts. Please wait a few minutes and try again.'; }
    $row = q_one('SELECT user_id, password_hash, account_status FROM user_account WHERE username_key = ?', [mb_strtolower(trim($username))]);
    $ok = password_verify($password, $row['password_hash'] ?? password_hash('timing-equaliser', PASSWORD_DEFAULT));
    $roles = $row ? q_all('SELECT 1 FROM user_role WHERE user_id = ? AND active_flag = 1', [$row['user_id']]) : [];
    if ($row && $ok && $row['account_status'] === 'ACTIVE' && $roles) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['user_id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        q('UPDATE user_account SET last_login_at = NOW() WHERE user_id = ?', [$row['user_id']]);
        current_user(true);
        audit('LOGIN_SUCCESS', 'user_account', (int) $row['user_id']);
        return null;
    }
    audit('LOGIN_FAILED', 'user_account', $row ? (int) $row['user_id'] : null, 'FAILED', [], $row ? (int) $row['user_id'] : null);
    return 'The username or password is incorrect, or the account is not active.';
}
function logout_now(): void {
    audit('LOGOUT', 'user_account', current_user()['user_id'] ?? null);
    $_SESSION = [];
    session_regenerate_id(true);
}
