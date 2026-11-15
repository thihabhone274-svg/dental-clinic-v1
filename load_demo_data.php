<?php
// OPTIONAL: loads clearly FICTIONAL demonstration data into a TEST installation (never use on real clinic data).
// Requires an existing administrator. Creates demo staff and doctor accounts with RANDOM passwords printed once.
// Run:   C:\xampp\php\php.exe scripts\load_demo_data.php --yes
require __DIR__ . '/_cli.php';
if (!in_array('--yes', $argv, true)) { exit("This adds fictional demo records. Re-run with --yes to confirm (do not do this on real data).\n"); }
$admin = q_one("SELECT u.user_id FROM user_account u JOIN user_role r ON r.user_id = u.user_id AND r.role_code = 'ADMIN' AND r.active_flag = 1 WHERE u.account_status = 'ACTIVE' ORDER BY u.user_id LIMIT 1");
if (!$admin) { exit("Create the first administrator first (scripts/create_first_admin.php).\n"); }
$aid = (int) $admin['user_id'];
$mk = function (string $user, string $name, string $role) use ($aid): array {
    if ($ex = q_one('SELECT user_id FROM user_account WHERE username_key = ?', [$user])) { return [(int) $ex['user_id'], null]; }
    $pw = bin2hex(random_bytes(6));
    $id = tx(function () use ($user, $name, $role, $pw, $aid) {
        q('INSERT INTO user_account (username, username_key, display_name, password_hash, must_change_password) VALUES (?,?,?,?,1)', [$user, $user, $name, password_hash($pw, PASSWORD_DEFAULT)]);
        $id = (int) db()->lastInsertId(); q('INSERT INTO user_role (user_id, role_code, assigned_by) VALUES (?,?,?)', [$id, $role, $aid]);
        if ($role === 'DOCTOR') { doctor_ensure_profile($id, $name); } audit('ACCOUNT_CREATED_BY_SETUP', 'user_account', $id, 'SUCCESS', ['mode' => 'demo_data']);
        return $id;
    });
    return [$id, $pw];
};
[$staffId, $spw] = $mk('demo.staff', 'Demo Staff', 'STAFF'); [$docId, $dpw] = $mk('demo.doctor', 'Demo Doctor', 'DOCTOR');
echo $spw ? "demo.staff password (shown once, must be changed at first sign-in): $spw\n" : "demo.staff already exists.\n";
echo $dpw ? "demo.doctor password (shown once, must be changed at first sign-in): $dpw\n" : "demo.doctor already exists.\n";
$people = [['Alex Demo-Sample', '1985-04-12', '555-0101'], ['Brook Demo-Sample', '1992-09-30', '555-0102'], ['Casey Demo-Sample', '1978-01-22', '555-0103'], ['Devon Demo-Sample', '2001-06-05', '555-0104']];
$doctor = (int) q_val('SELECT doctor_id FROM doctor WHERE user_id = ?', [$docId]); $i = 0;
foreach ($people as [$n, $dob, $ph]) {
    $err = []; $d = patient_validate(['full_name' => $n, 'date_of_birth' => $dob, 'phone' => $ph, 'registration_date' => date('Y-m-d'), 'nationality' => 'Fictional'], $err, true);
    if (q_val('SELECT 1 FROM patient WHERE name_key = ?', [normalize_name($n)])) { continue; }
    $pid = patient_create($d, $staffId, false);
    if ($i === 0) { med_record($pid, ['allergy_status' => 'HAS_ALLERGIES', 'past_medical_history_status' => 'NOT_ASKED', 'past_medical_history' => null, 'medication_status' => 'NOT_ASKED', 'regular_medication' => null, 'additional_note' => 'Fictional demo record.'], [['allergen_name' => 'Demo allergen', 'severity' => 'MILD', 'reaction_note' => null]], $staffId); }
    $start = date('Y-m-d', strtotime('+' . ($i + 1) . ' day')) . ' 10:00:00';
    appt_create(['patient_id' => $pid, 'doctor_id' => $doctor, 'start_at' => $start, 'end_at' => date('Y-m-d H:i:s', strtotime($start . ' +30 minutes')), 'reason' => 'Demo check-up', 'note' => null], $staffId, false);
    $i++;
}
echo "Fictional demo data loaded: $i patient(s) with appointments.\n";
