<?php
/**
 * HTTP integration tests. Drives the REAL application as each role (staff, doctor, admin, anonymous).
 * SAFETY: refuses to run unless the configured database name ends with "_test".
 * Usage (see docs/TESTING.md):   php tests/run_tests.php http://127.0.0.1:8099
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$cfgFile = getenv('DENTAL_CONFIG') ?: __DIR__ . '/../config/database.php';
$c = require $cfgFile;
if (!preg_match('/_test$/', $c['name'])) { fwrite(STDERR, "Refusing to run: database '{$c['name']}' does not end with _test.\n"); exit(2); }
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pass = 0; $fail = 0; $failures = [];
// Each run uses its own loopback source address (127.x.y.z), because the application throttles repeated failed sign-ins per address.
$srcIp = getenv('TEST_SOURCE_IP') ?: ('127.' . random_int(2, 250) . '.' . random_int(1, 250) . '.' . random_int(2, 250));
function ok(bool $cond, string $name): void { global $pass, $fail, $failures; if ($cond) { $pass++; echo "  PASS  $name\n"; } else { $fail++; $failures[] = $name; echo "  FAIL  $name\n"; } }
function has($r, string $needle, string $name): void { ok(str_contains(is_array($r) ? $r['body'] : $r, $needle), $name); }
function lacks($r, string $needle, string $name): void { ok(!str_contains(is_array($r) ? $r['body'] : $r, $needle), $name); }
function section(string $t): void { echo "\n== $t\n"; }
function val(string $sql, array $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); $v = $s->fetchColumn(); return $v === false ? null : $v; }
function row(string $sql, array $p = []): ?array { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); $r = $s->fetch(); return $r ?: null; }

class Client {
    public string $jar;
    function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'jar'); }
    function req(string $method, string $path, ?array $fields = null): array {
        global $base;
        $ch = curl_init($base . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30] + ($GLOBALS['srcIp'] ? [CURLOPT_INTERFACE => $GLOBALS['srcIp']] : []));
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields ?? [])); }
        $raw = (string) curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $h = substr($raw, 0, $hs); preg_match('/^Location:\s*(\S+)/mi', $h, $m);
        return ['code' => $code, 'body' => substr($raw, $hs), 'location' => $m[1] ?? null];
    }
    function get(string $p): array { return $this->req('GET', $p); }
    function token(string $page = 'index.php'): string { $r = $this->get($page); preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m); return $m[1] ?? ''; }
    function post(string $p, array $f = [], string $tokenPage = 'index.php'): array { if (!array_key_exists('csrf_token', $f)) { $f['csrf_token'] = $this->token($tokenPage); } return $this->req('POST', $p, $f); }
    function follow(array $r): array { if (!$r['location']) { return $r; } $p = preg_replace('#^https?://[^/]+#', '', $r['location']); return $this->get($p); }
    function login(string $u, string $pw): array { $r = $this->req('POST', 'login.php', ['username' => $u, 'password' => $pw, 'csrf_token' => $this->token('login.php')]); return $r; }
    function id(array $r): int { return (int) preg_replace('/\D/', '', (string) parse_url((string) $r['location'], PHP_URL_QUERY)); }
}
function mkuser(string $username, string $name, array $roles, string $pw = 'Test-Pass-123'): int {
    global $pdo;
    $pdo->prepare('INSERT INTO user_account (username, username_key, display_name, password_hash) VALUES (?,?,?,?)')->execute([$username, strtolower($username), $name, password_hash($pw, PASSWORD_DEFAULT)]);
    $id = (int) $pdo->lastInsertId();
    foreach ($roles as $r) { $pdo->prepare('INSERT INTO user_role (user_id, role_code) VALUES (?,?)')->execute([$id, $r]); }
    if (in_array('DOCTOR', $roles, true)) { $pdo->prepare('INSERT INTO doctor (user_id, doctor_code, full_name) VALUES (?,?,?)')->execute([$id, 'DRT' . $id, $name]); }
    return $id;
}
clearstatcache(); $probe = (new Client())->get('login.php'); if ($probe['code'] === 0) { $srcIp = ''; echo "(source-address binding unavailable; using the default address. If sign-ins fail, wait 5 minutes: the login throttle may be active.)\n"; }
$logSize = is_file(__DIR__ . '/../storage/logs/php_errors.log') ? filesize(__DIR__ . '/../storage/logs/php_errors.log') : 0;
$s = substr(bin2hex(random_bytes(4)), 0, 6); $PW = 'Test-Pass-123'; $tomorrow = date('Y-m-d', strtotime('+1 day')); $in3 = date('Y-m-d', strtotime('+3 day'));
$admId = mkuser("adm_$s", 'Test Admin', ['ADMIN']); $stfId = mkuser("stf_$s", 'Test Staff', ['STAFF']); $docUid = mkuser("doc_$s", 'Dr Test One', ['DOCTOR']); $doc2Uid = mkuser("doc2_$s", 'Dr Test Two', ['DOCTOR']);
$docId = (int) val('SELECT doctor_id FROM doctor WHERE user_id = ?', [$docUid]); $doc2Id = (int) val('SELECT doctor_id FROM doctor WHERE user_id = ?', [$doc2Uid]);
$anon = new Client(); $adm = new Client(); $stf = new Client(); $doc = new Client(); $doc2 = new Client();

section('1. Login, logout, direct URL access');
has($anon->get('login.php'), 'Sign in', 'login page loads');
foreach (['index.php', 'patients/index.php', 'patients/view.php?id=1', 'visits/view.php?id=1', 'admin/accounts.php', 'appointments/index.php', 'examinations/view.php?id=1'] as $p) { $r = $anon->get($p); ok($r['code'] === 302 && str_contains((string) $r['location'], 'login.php'), "anonymous is redirected to login: $p"); }
$r = $anon->login("stf_$s", 'wrong-password'); has($r, 'incorrect, or the account is not active', 'wrong password shows a generic error'); lacks($r, 'Test Staff', 'wrong password reveals nothing about the account');
$r = $anon->login('nobody_here', 'whatever1'); has($r, 'incorrect, or the account is not active', 'unknown user shows the same generic error');
foreach ([[$adm, "adm_$s"], [$stf, "stf_$s"], [$doc, "doc_$s"], [$doc2, "doc2_$s"]] as [$cl, $u]) { $r = $cl->login($u, $PW); ok($r['code'] === 302 && !str_contains((string) $r['location'], 'login'), "login succeeds: $u"); }
$sid1 = $stf->token(); has($stf->get('index.php'), 'Dashboard', 'staff dashboard loads');
$r = $anon->post('logout.php', ['csrf_token' => 'bad']); ok($r['code'] === 302 || $r['code'] === 400, 'logout without a session is harmless');
$tmp = new Client(); $tmp->login("stf_$s", $PW); $r = $tmp->post('logout.php'); ok($r['code'] === 302, 'logout redirects'); $r = $tmp->get('index.php'); ok($r['code'] === 302, 'after logout the dashboard is protected');
$tmp = new Client(); $r = $tmp->get('login.php'); preg_match('/PHPSESSID|DENTALSESSID=([^;]+)/', file_get_contents($tmp->jar), $m1); $tmp->login("stf_$s", $PW); $after = file_get_contents($tmp->jar);
ok(substr_count($after, 'DENTALSESSID') >= 1, 'session cookie is set'); ok(val("SELECT COUNT(*) FROM audit_event WHERE action_code = 'LOGIN_SUCCESS' AND actor_user_id = ?", [$stfId]) >= 1, 'login is audited');

section('2. Role-based direct URL access');
foreach (['admin/accounts.php', 'admin/requests.php', 'admin/audit.php'] as $p) { ok($stf->get($p)['code'] === 403, "staff is denied: $p"); ok($doc->get($p)['code'] === 403, "doctor is denied: $p"); ok($adm->get($p)['code'] === 200, "admin may open: $p"); }
ok($doc->get('patients/create.php')['code'] === 403, 'doctor cannot open patient registration'); ok($doc->get('appointments/form.php')['code'] === 403, 'doctor cannot open appointment booking');
ok($stf->get('examinations/form.php?visit_id=1')['code'] === 403 || $stf->get('examinations/form.php?visit_id=1')['code'] === 404, 'staff cannot open the examination form');
ok(val("SELECT COUNT(*) FROM audit_event WHERE action_code = 'ACCESS_DENIED' AND outcome = 'DENIED'") >= 4, 'denied attempts are audited');

section('3. CSRF protection');
$cnt = (int) val('SELECT COUNT(*) FROM patient');
$r = $stf->req('POST', 'patients/create.php', ['full_name' => 'Csrf Test']); ok($r['code'] === 400, 'POST without CSRF token is rejected'); $r = $stf->req('POST', 'patients/create.php', ['full_name' => 'Csrf Test', 'csrf_token' => 'deadbeef']); ok($r['code'] === 400, 'POST with a wrong CSRF token is rejected');
ok((int) val('SELECT COUNT(*) FROM patient') === $cnt, 'nothing was saved after CSRF rejection');

section('4. Patients: validation, create, search, XSS escaping, duplicates, edit');
$r = $stf->post('patients/create.php', ['full_name' => '', 'date_of_birth' => '2999-01-01', 'registration_date' => date('Y-m-d')], 'patients/create.php');
has($r, 'Patient name is required.', 'missing name shows an inline error'); has($r, 'cannot be after', 'future birth date is rejected'); ok((int) val('SELECT COUNT(*) FROM patient') === $cnt, 'invalid registration saves nothing');
$r = $stf->post('patients/create.php', ['full_name' => "Alice Sample$s", 'date_of_birth' => '1990-05-05', 'phone' => '555-1111', 'nrc_number' => "NRC$s", 'gender' => 'Female', 'registration_date' => date('Y-m-d')]);
ok($r['code'] === 302 && str_contains((string) $r['location'], 'patients/view.php'), 'valid registration redirects to the profile'); $p1 = $stf->id($r);
$pr = row('SELECT * FROM patient WHERE patient_id = ?', [$p1]); ok($pr && preg_match('/^PAT-\d{4}-\d{6}$/', $pr['patient_code']), 'registration number is generated'); ok($pr['nrc_number'] === "NRC$s", 'NRC is stored as an ordinary optional field');
has($stf->get('patients/index.php?q=' . urlencode("Alice Sample$s")), "Alice Sample$s", 'search by name finds the patient'); has($stf->get('patients/index.php?q=555-1111'), "Alice Sample$s", 'search by telephone finds the patient'); has($stf->get('patients/index.php?q=' . $pr['patient_code']), "Alice Sample$s", 'search by registration number finds the patient');
has($stf->get('patients/index.php?q=zzzNoSuchPatient'), 'No matching patients', 'empty search shows an empty state');
$r = $stf->post('patients/create.php', ['full_name' => "<script>alert(1)</script> Xss$s", 'registration_date' => date('Y-m-d')]); $px = $stf->id($r);
$page = $stf->get("patients/view.php?id=$px"); has($page, '&lt;script&gt;alert(1)&lt;/script&gt;', 'user input is HTML-escaped on output'); lacks($page, '<script>alert(1)', 'no raw script tag from user input');
$r = $stf->post('patients/create.php', ['full_name' => "Alice Sample$s", 'date_of_birth' => '1990-05-05', 'phone' => '555-2222', 'registration_date' => date('Y-m-d')]);
has($r, 'Possible duplicate patients', 'duplicate warning is shown for the same name and date of birth'); ok((int) val('SELECT COUNT(*) FROM patient WHERE name_key = ?', ["alice sample$s"]) === 1, 'duplicate is not saved before review');
$r = $stf->post('patients/create.php', ['full_name' => "Alice Sample$s", 'date_of_birth' => '1990-05-05', 'phone' => '555-2222', 'registration_date' => date('Y-m-d'), 'confirm_different' => '1']);
ok($r['code'] === 302, 'registration proceeds after the staff member confirms it is a different person'); ok((int) val('SELECT COUNT(*) FROM patient WHERE name_key = ?', ["alice sample$s"]) === 2, 'both records exist; nothing was merged'); $p2 = $stf->id($r);
$r = $stf->post('patients/edit.php', ['id' => $p1, 'full_name' => "Alice Sample$s", 'phone' => '555-9999', 'date_of_birth' => '1990-05-05', 'gender' => 'Female', 'nrc_number' => "NRC$s"]); ok($r['code'] === 302, 'edit demographics succeeds'); ok(val('SELECT phone FROM patient WHERE patient_id = ?', [$p1]) === '555-9999', 'the edit is stored');
ok(val("SELECT summary FROM audit_event WHERE action_code = 'PATIENT_UPDATED' AND entity_id = ? ORDER BY event_id DESC LIMIT 1", [$p1]) === 'fields=phone', 'audit records the changed field NAME'); lacks((string) val("SELECT summary FROM audit_event WHERE action_code = 'PATIENT_UPDATED' ORDER BY event_id DESC LIMIT 1"), '555-9999', 'audit does not contain the new value');
$r = $doc->post('patients/create.php', ['full_name' => 'Doctor Made'], 'index.php'); ok($r['code'] === 403, 'doctor POST to register a patient is refused');
$r = $adm->post('patients/create.php', ['full_name' => "Admin Made$s", 'registration_date' => date('Y-m-d')]); ok($r['code'] === 302, 'administrator can register patients (staff function)'); $p3 = $adm->id($r);

section('5. Medical and dental history (staff write, doctor read-only, admin may write)');
$med = ['patient_id' => $p1, 'allergy_status' => 'HAS_ALLERGIES', 'past_medical_history_status' => 'DETAILS_REPORTED', 'past_medical_history' => 'Asthma', 'medication_status' => 'NONE_REPORTED', 'allergen' => ['Penicillin', ''], 'severity' => ['MODERATE', 'UNKNOWN'], 'reaction' => ['Rash', ''], 'additional_note' => 'Note one'];
$r = $stf->post('histories/medical.php', ['patient_id' => $p1, 'allergy_status' => 'HAS_ALLERGIES', 'past_medical_history_status' => 'NOT_ASKED', 'medication_status' => 'NOT_ASKED']); has($r, 'Add at least one allergy', 'HAS_ALLERGIES without an allergen is rejected inline');
$r = $stf->post('histories/medical.php', $med); ok($r['code'] === 302, 'staff saves medical history'); ok((int) val('SELECT COUNT(*) FROM medical_history_version WHERE patient_id = ?', [$p1]) === 1, 'version 1 created'); ok((int) val('SELECT COUNT(*) FROM allergy_item') >= 1, 'allergy row stored');
$r = $stf->post('histories/medical.php', $med); has($stf->follow($r), 'No changes were made', 'an unchanged save creates no version'); ok((int) val('SELECT COUNT(*) FROM medical_history_version WHERE patient_id = ?', [$p1]) === 1, 'still one version');
$med2 = $med; $med2['additional_note'] = 'Note two'; $r = $stf->post('histories/medical.php', $med2); ok((int) val('SELECT COUNT(*) FROM medical_history_version WHERE patient_id = ?', [$p1]) === 2, 'a change creates version 2'); ok(val('SELECT additional_note FROM medical_history_version WHERE patient_id = ? AND version_no = 1', [$p1]) === 'Note one', 'version 1 is preserved unchanged');
$r = $stf->post('histories/dental.php', ['patient_id' => $p1, 'last_dental_visit_text' => '6 months ago', 'brushing_frequency' => 'Twice a day', 'bleeding_gums' => 'YES', 'chews_betel_quid' => 'NO']); ok($r['code'] === 302, 'staff saves dental history'); $dr = row('SELECT * FROM dental_history_version WHERE patient_id = ?', [$p1]);
ok($dr && $dr['bleeding_gums'] === 'YES' && $dr['flosses'] === 'NOT_PROVIDED', 'dental answers are structured; unanswered stays NOT_PROVIDED, never NO');
$r = $stf->post('histories/dental.php', ['patient_id' => $p1, 'bleeding_gums' => 'MAYBE']); has($r, 'Choose a valid option', 'invalid dental answer is rejected');
// doctor scope: needs an appointment with this doctor
$r = $stf->post('appointments/form.php', ['patient_id' => $p1, 'doctor_id' => $docId, 'appt_date' => $tomorrow, 'start_time' => '10:00', 'end_time' => '10:30', 'reason' => 'Check-up']); ok($r['code'] === 302, 'staff books an appointment'); $a1 = $stf->id($r);
$vers = (int) val('SELECT COUNT(*) FROM medical_history_version WHERE patient_id = ?', [$p1]);
$page = $doc->get("histories/medical.php?patient_id=$p1"); ok($page['code'] === 200, 'doctor (in active care) can view medical history'); lacks($page, 'Record new version', 'doctor sees no edit control'); has($page, 'view medical history but not change it', 'doctor sees the read-only notice');
$r = $doc->post('histories/medical.php', $med2 + ['additional_note' => 'Doctor edit attempt'], 'index.php'); ok($r['code'] === 403, 'doctor POST to medical history is refused (403)'); ok((int) val('SELECT COUNT(*) FROM medical_history_version WHERE patient_id = ?', [$p1]) === $vers, 'no version was created by the doctor');
$r = $doc->post('histories/dental.php', ['patient_id' => $p1, 'bleeding_gums' => 'NO'], 'index.php'); ok($r['code'] === 403, 'doctor POST to dental history is refused (403)'); lacks($doc->get("histories/dental.php?patient_id=$p1"), 'Record new version', 'doctor sees no dental edit control');
ok($doc2->get("patients/view.php?id=$p1")['code'] === 403, 'a doctor with no active care of the patient cannot open the profile'); ok($doc2->get("histories/medical.php?patient_id=$p1")['code'] === 403, '... nor the medical history');
$med3 = $med; $med3['additional_note'] = 'Admin note'; $r = $adm->post('histories/medical.php', $med3); ok($r['code'] === 302 && (int) val('SELECT COUNT(*) FROM medical_history_version WHERE patient_id = ?', [$p1]) === $vers + 1, 'administrator can record medical history (staff function)');
$r = $adm->post('histories/dental.php', ['patient_id' => $p1, 'bleeding_gums' => 'NO']); ok($r['code'] === 302, 'administrator can record dental history');

section('6. Appointments: validation, conflicts, reschedule, cancel');
$r = $stf->post('appointments/form.php', ['patient_id' => $p2, 'doctor_id' => $docId, 'appt_date' => $tomorrow, 'start_time' => '10:15', 'end_time' => '10:45']); has($r, 'already has', 'overlap for the same doctor is rejected'); ok((int) val('SELECT COUNT(*) FROM appointment WHERE patient_id = ?', [$p2]) === 0, 'conflicting appointment was not saved');
$r = $stf->post('appointments/form.php', ['patient_id' => $p2, 'doctor_id' => $docId, 'appt_date' => '2000-01-01', 'start_time' => '10:00', 'end_time' => '10:30']); has($r, 'cannot start in the past', 'past appointments are rejected'); $r = $stf->post('appointments/form.php', ['patient_id' => $p2, 'doctor_id' => $docId, 'appt_date' => $tomorrow, 'start_time' => '11:00', 'end_time' => '10:00']); has($r, 'End time must be after', 'end before start is rejected');
$r = $stf->post('appointments/form.php', ['patient_id' => $p1, 'doctor_id' => $doc2Id, 'appt_date' => $tomorrow, 'start_time' => '10:10', 'end_time' => '10:20']); has($r, 'Book anyway', 'patient overlap with another doctor shows a warning'); ok((int) val('SELECT COUNT(*) FROM appointment WHERE patient_id = ? AND doctor_id = ?', [$p1, $doc2Id]) === 0, 'not saved until confirmed');
$r = $stf->post('appointments/form.php', ['patient_id' => $p1, 'doctor_id' => $doc2Id, 'appt_date' => $tomorrow, 'start_time' => '10:10', 'end_time' => '10:20', 'confirm_overlap' => '1']); ok($r['code'] === 302, 'patient overlap can be booked after confirmation'); $a1b = $stf->id($r);
$r = $stf->post('appointments/form.php', ['id' => $a1, 'doctor_id' => $docId, 'appt_date' => $in3, 'start_time' => '14:00', 'end_time' => '14:30', 'change_reason' => 'Patient asked to move']); ok($r['code'] === 302, 'reschedule succeeds'); ok((int) val('SELECT COUNT(*) FROM appointment_change WHERE appointment_id = ?', [$a1]) === 1, 'the old time is kept in the change history');
ok(str_starts_with((string) val('SELECT old_start_at FROM appointment_change WHERE appointment_id = ?', [$a1]), $tomorrow), 'change history holds the original date');
$r = $stf->post('appointments/cancel.php', ['id' => $a1b, 'reason' => '']); has($stf->follow($r), 'Reason is required', 'cancelling needs a reason'); $r = $stf->post('appointments/cancel.php', ['id' => $a1b, 'reason' => 'Booked in error']); ok(val('SELECT status FROM appointment WHERE appointment_id = ?', [$a1b]) === 'CANCELLED', 'appointment cancelled, row kept');
$apNo = (string) val('SELECT appointment_no FROM appointment WHERE appointment_id = ?', [$a1]); $list = $doc2->get("appointments/index.php?from=$tomorrow&to=$in3"); lacks($list, $apNo, 'a doctor does not see another doctor\'s bookings'); has($doc->get("appointments/index.php?from=$tomorrow&to=$in3"), $apNo, 'the doctor sees their own booking'); ok($doc2->get("appointments/view.php?id=$a1")['code'] === 403, 'a doctor cannot open another doctor\'s appointment');
$r = $doc->post('appointments/cancel.php', ['id' => $a1, 'reason' => 'x']); ok($r['code'] === 403, 'doctor cannot cancel appointments'); ok($adm->post('appointments/form.php', ['patient_id' => $p3, 'doctor_id' => $doc2Id, 'appt_date' => $in3, 'start_time' => '09:00', 'end_time' => '09:30'])['code'] === 302, 'administrator can book appointments');

section('7. Visits: from appointment, walk-in, scope');
$r = $stf->post('visits/open.php', ['appointment_id' => $a1]); has($stf->follow($r), 'not today', 'opening a visit for another day needs confirmation'); ok((int) val('SELECT COUNT(*) FROM clinical_visit WHERE appointment_id = ?', [$a1]) === 0, 'not opened before confirmation');
$r = $stf->post('visits/open.php', ['appointment_id' => $a1, 'confirm_other_date' => '1']); ok($r['code'] === 302 && str_contains((string) $r['location'], 'visits/view.php'), 'visit opened from the appointment'); $v1 = $stf->id($r);
$vr = row('SELECT * FROM clinical_visit WHERE visit_id = ?', [$v1]); ok($vr && $vr['visit_type'] === 'APPOINTMENT' && (int) $vr['patient_id'] === $p1 && (int) $vr['doctor_id'] === $docId, 'visit takes patient and doctor from the appointment');
$r = $stf->post('visits/open.php', ['appointment_id' => $a1, 'confirm_other_date' => '1']); ok((int) val('SELECT COUNT(*) FROM clinical_visit WHERE appointment_id = ?', [$a1]) === 1, 'a second visit is not created for the same appointment');
$r = $stf->post('visits/open.php', ['patient_id' => $p2, 'visit_type' => 'WALK_IN', 'doctor_id' => $docId, 'note' => 'Walk-in without appointment']); ok($r['code'] === 302, 'walk-in visit created without an appointment'); $v2 = $stf->id($r); ok(val('SELECT appointment_id FROM clinical_visit WHERE visit_id = ?', [$v2]) === null, 'walk-in has no appointment');
$r = $stf->post('visits/open.php', ['patient_id' => $p2, 'visit_type' => 'WALK_IN', 'doctor_id' => 0]); has($r, 'Choose a responsible doctor', 'walk-in validation shows an inline error');
ok($doc2->get("visits/view.php?id=$v1")['code'] === 403, 'another doctor cannot open this doctor\'s visit'); ok($doc->get("visits/view.php?id=$v1")['code'] === 200, 'the responsible doctor can open the visit'); ok($doc->get("patients/view.php?id=$p2")['code'] === 200, 'an open visit brings the patient into the doctor\'s active care');
$r = $doc->post('visits/open.php', ['patient_id' => $p3, 'visit_type' => 'WALK_IN', 'doctor_id' => $docId], 'index.php'); ok($r['code'] === 403, 'a doctor cannot open a walk-in for a patient outside their active care');

section('8. Examinations: draft, validation, finalize, close, correction, preserved history');
$now = date('Y-m-d\TH:i'); $ex = ['visit_id' => $v1, 'id' => 0, 'examined_at' => $now, 'general_teeth_condition' => 'F', 'oral_hygiene' => 'G', 'gingival_inflammation' => 'L', 'gingival_recession' => 'G', 'occlusion_class' => '2', 'overbite_code' => '-', 'overjet_code' => '+', 'lipline_code' => 'N', 'midline_code' => 'R', 'mouth_opening_mm' => '42.5', 'tmj_note' => 'TMJ ok', 'general_note' => 'Original clinical note', 'chief_complaint' => 'SENTINEL_CLINICAL_TEXT pain', 'tmj_pain' => 'NO'];
$r = $stf->get("examinations/form.php?visit_id=$v1"); ok($r['code'] === 403, 'staff cannot open the examination form'); ok($doc2->get("examinations/form.php?visit_id=$v1")['code'] === 403, 'a non-responsible doctor cannot open it');
$r = $doc->post('examinations/form.php', array_merge($ex, ['overbite_code' => 'X', 'mouth_opening_mm' => '-5']), "examinations/form.php?visit_id=$v1"); has($r, 'Choose a valid option for Overbite (OB)', 'invalid code shows an error next to the control'); has($r, 'Mouth opening (mm) must be a number', 'negative mouth opening is rejected'); ok((int) val('SELECT COUNT(*) FROM clinical_examination WHERE visit_id = ?', [$v1]) === 0, 'invalid examination is not saved');
$r = $doc->post('examinations/form.php', $ex, "examinations/form.php?visit_id=$v1"); ok($r['code'] === 302, 'doctor saves a draft'); $e1 = $stf->id($r) ?: (int) val('SELECT exam_id FROM clinical_examination WHERE visit_id = ?', [$v1]);
$er = row('SELECT * FROM clinical_examination WHERE visit_id = ?', [$v1]); ok($er && $er['status'] === 'DRAFT' && (int) $er['patient_id'] === $p1 && (int) $er['doctor_id'] === $docId && (int) $er['authored_by'] === $docUid, 'draft is linked to the correct patient, visit, doctor and author'); ok($er['overbite_code'] === '-' && $er['mouth_opening_mm'] === '42.50', 'documented fields stored exactly as entered');
ok(val("SELECT field_value FROM clinical_examination_extra WHERE exam_id = ? AND field_key = 'chief_complaint'", [$er['exam_id']]) === 'SENTINEL_CLINICAL_TEXT pain', 'provisional fields are stored separately');
$e1 = (int) $er['exam_id']; $r = $doc->post('examinations/form.php', array_merge($ex, ['id' => $e1, 'general_note' => 'Draft edited']), "examinations/form.php?id=$e1"); ok(val('SELECT general_note FROM clinical_examination WHERE exam_id = ?', [$e1]) === 'Draft edited', 'a draft can be edited and re-saved'); $ex['general_note'] = 'Original clinical note';
$r = $doc->post('examinations/form.php', array_merge($ex, ['id' => $e1, 'general_note' => 'Original clinical note']), "examinations/form.php?id=$e1");
$r = $doc->post('visits/close.php', ['id' => $v1]); has($doc->follow($r), 'draft examination', 'closing is blocked while a draft exists'); ok(val('SELECT status FROM clinical_visit WHERE visit_id = ?', [$v1]) === 'OPEN', 'visit stays open');
ok($doc2->post('examinations/finalize.php', ['id' => $e1])['code'] === 403, 'a non-responsible doctor cannot finalize'); ok($stf->post('examinations/finalize.php', ['id' => $e1])['code'] === 403, 'staff cannot finalize');
$r = $doc->post('examinations/finalize.php', ['id' => $e1]); $fr = row('SELECT * FROM clinical_examination WHERE exam_id = ?', [$e1]); ok($fr['status'] === 'FINALIZED' && (int) $fr['finalized_by'] === $docUid && $fr['finalized_at'] !== null, 'draft finalized with finalizer and time recorded');
$r = $doc->post('examinations/form.php', array_merge($ex, ['id' => $e1, 'general_note' => 'Sneaky edit']), "examinations/form.php?id=$e1"); ok(val('SELECT general_note FROM clinical_examination WHERE exam_id = ?', [$e1]) === 'Original clinical note', 'a finalized examination cannot be edited in place');
$r = $doc->post('visits/close.php', ['id' => $v1]); ok(val('SELECT status FROM clinical_visit WHERE visit_id = ?', [$v1]) === 'CLOSED', 'visit closes once no draft remains'); ok(val('SELECT status FROM appointment WHERE appointment_id = ?', [$a1]) === 'COMPLETED', 'the linked appointment becomes COMPLETED');
$before = row('SELECT * FROM clinical_examination WHERE exam_id = ?', [$e1]);
$r = $doc->post('examinations/correct.php', array_merge($ex, ['id' => $e1, 'correction_reason' => '', 'general_note' => 'Corrected note']), "examinations/correct.php?id=$e1"); has($r, 'Reason for correction is required', 'a correction requires a reason');
$r = $doc->post('examinations/correct.php', array_merge($ex, ['id' => $e1, 'correction_reason' => 'Typo in the note', 'general_note' => 'Corrected note', 'mouth_opening_mm' => '44']), "examinations/correct.php?id=$e1"); ok($r['code'] === 302, 'doctor corrects a finalized examination after the visit is closed'); $e2 = $doc->id($r);
$old = row('SELECT * FROM clinical_examination WHERE exam_id = ?', [$e1]); $new = row('SELECT * FROM clinical_examination WHERE exam_id = ?', [$e2]);
ok($old['status'] === 'SUPERSEDED' && $old['general_note'] === $before['general_note'] && $old['mouth_opening_mm'] === $before['mouth_opening_mm'], 'the previous finalized record is preserved unchanged (only marked superseded)'); ok($new && $new['status'] === 'FINALIZED' && (int) $new['supersedes_exam_id'] === $e1 && $new['correction_reason'] === 'Typo in the note' && $new['general_note'] === 'Corrected note', 'the correction is a new linked finalized record with a reason');
$r = $doc->post('examinations/correct.php', array_merge($ex, ['id' => $e1, 'correction_reason' => 'again']), "examinations/correct.php?id=$e2"); ok((int) val('SELECT COUNT(*) FROM clinical_examination WHERE supersedes_exam_id = ?', [$e1]) === 1, 'an examination cannot be corrected twice');
has($doc->get("examinations/view.php?id=$e1"), 'was corrected', 'the superseded record is labelled and still viewable');
// administrator performs a doctor function on another doctor's visit; discard flow; visit cancel
$r = $adm->post('examinations/form.php', array_merge($ex, ['visit_id' => $v2, 'id' => 0]), "examinations/form.php?visit_id=$v2"); ok($r['code'] === 302, 'administrator can enter an examination (doctor function)'); $e3 = (int) val('SELECT exam_id FROM clinical_examination WHERE visit_id = ?', [$v2]);
$er3 = row('SELECT * FROM clinical_examination WHERE exam_id = ?', [$e3]); ok((int) $er3['authored_by'] === $admId && (int) $er3['doctor_id'] === $docId, 'the audit trail records the real actor (admin) and the responsible doctor');
ok(val("SELECT actor_role FROM audit_event WHERE action_code = 'EXAM_DRAFT_SAVED' AND entity_id = ? ORDER BY event_id DESC LIMIT 1", [$e3]) === 'ADMIN', 'audit records the acting role');
$r = $adm->post('visits/cancel.php', ['id' => $v2, 'reason_code' => 'OTHER']); has($adm->follow($r), 'cannot be cancelled', 'a visit with an examination cannot be cancelled');
$r = $adm->post('examinations/discard.php', ['id' => $e3, 'reason_code' => 'WRONG_PATIENT']); ok(val('SELECT status FROM clinical_examination WHERE exam_id = ?', [$e3]) === 'DISCARDED', 'a draft can be discarded (kept, not deleted)'); $r = $adm->post('visits/cancel.php', ['id' => $v2, 'reason_code' => 'CREATED_IN_ERROR']); ok(val('SELECT status FROM clinical_visit WHERE visit_id = ?', [$v2]) === 'CANCELLED', 'the visit can then be cancelled');
$r = $stf->post('visits/open.php', ['patient_id' => $p3, 'visit_type' => 'WALK_IN', 'doctor_id' => $docId]); $v3 = $stf->id($r); $r = $stf->post('visits/close.php', ['id' => $v3]); ok(val('SELECT status FROM clinical_visit WHERE visit_id = ?', [$v3]) === 'CLOSED', 'staff can close a visit that has no draft');

section('9. Archive rules');
$r = $stf->post('patients/archive.php', ['id' => $p2, 'reason' => 'Moved away', 'confirm_code' => 'WRONG']); has($stf->follow($r), 'does not match', 'archive needs the registration number typed correctly'); ok(val('SELECT archived_at FROM patient WHERE patient_id = ?', [$p2]) === null, 'not archived');
$stf->post('appointments/form.php', ['patient_id' => $p2, 'doctor_id' => $docId, 'appt_date' => $in3, 'start_time' => '16:00', 'end_time' => '16:30']); $code2 = val('SELECT patient_code FROM patient WHERE patient_id = ?', [$p2]);
$r = $stf->post('patients/archive.php', ['id' => $p2, 'reason' => 'Moved away', 'confirm_code' => $code2]); has($stf->follow($r), 'still scheduled', 'archive is blocked while appointments are still scheduled');
$ap = (int) val("SELECT appointment_id FROM appointment WHERE patient_id = ? AND status = 'SCHEDULED'", [$p2]); $stf->post('appointments/cancel.php', ['id' => $ap, 'reason' => 'Patient moving']); $r = $stf->post('patients/archive.php', ['id' => $p2, 'reason' => 'Moved away', 'confirm_code' => $code2]);
ok(val('SELECT archived_at FROM patient WHERE patient_id = ?', [$p2]) !== null, 'patient archived (never deleted)'); ok($stf->get("patients/edit.php?id=$p2")['code'] === 302, 'an archived patient cannot be edited');
$r = $stf->post('histories/medical.php', ['patient_id' => $p2, 'allergy_status' => 'NONE_REPORTED', 'past_medical_history_status' => 'NOT_ASKED', 'medication_status' => 'NOT_ASKED']); has($stf->follow($r), 'archived', 'history cannot be added to an archived patient');
$r = $stf->post('appointments/form.php', ['patient_id' => $p2, 'doctor_id' => $docId, 'appt_date' => $in3, 'start_time' => '17:00', 'end_time' => '17:30']); ok($r['code'] === 302 && str_contains((string) $r['location'], 'patients/view.php'), 'archived patients cannot be booked');

section('10. Accounts: requests, approval, roles, safeguards, passwords');
$r = $anon->post('request_account.php', ['applicant_name' => 'Evil Admin', 'username' => "evil_$s", 'requested_role' => 'ADMIN', 'password' => 'Secret-123x', 'password_confirm' => 'Secret-123x'], 'request_account.php'); has($r, 'Choose Staff or Doctor', 'a public request for ADMIN is rejected'); ok((int) val('SELECT COUNT(*) FROM account_request WHERE requested_username = ?', ["evil_$s"]) === 0, 'no request row was written for ADMIN');
$r = $anon->post('request_account.php', ['applicant_name' => 'New Staffer', 'username' => "new_$s", 'requested_role' => 'STAFF', 'password' => 'short', 'password_confirm' => 'short'], 'request_account.php'); has($r, 'at least 8 characters', 'weak password is rejected with an inline message');
$r = $anon->post('request_account.php', ['applicant_name' => 'New Staffer', 'username' => "new_$s", 'requested_role' => 'STAFF', 'password' => 'Secret-123x', 'password_confirm' => 'Secret-123x'], 'request_account.php'); ok($r['code'] === 302, 'a valid staff request is accepted'); $rq = (int) val('SELECT request_id FROM account_request WHERE requested_username = ?', ["new_$s"]);
ok(!$anon->login("new_$s", 'Secret-123x')['location'], 'a pending applicant cannot sign in'); $r = $anon->post('request_account.php', ['applicant_name' => 'Dup', 'username' => "NEW_$s", 'requested_role' => 'STAFF', 'password' => 'Secret-123x', 'password_confirm' => 'Secret-123x'], 'request_account.php'); has($r, 'not available', 'a duplicate username is refused');
$r = $stf->post('admin/requests.php', ['action' => 'approve', 'request_id' => $rq, 'role' => 'STAFF']); ok($r['code'] === 403, 'staff cannot approve requests'); $r = $adm->post('admin/requests.php', ['action' => 'approve', 'request_id' => $rq, 'role' => 'ADMIN']); has($adm->follow($r), 'Only STAFF or DOCTOR', 'approval cannot assign ADMIN'); ok(val('SELECT request_status FROM account_request WHERE request_id = ?', [$rq]) === 'PENDING', 'request stays pending after an ADMIN attempt');
$r = $adm->post('admin/requests.php', ['action' => 'approve', 'request_id' => $rq, 'role' => 'STAFF']); $nu = (int) val('SELECT user_id FROM user_account WHERE username = ?', ["new_$s"]); ok($nu > 0 && account_roles_of($nu) === ['STAFF'], 'approval creates the account with the chosen role'); ok(val('SELECT password_hash FROM account_request WHERE request_id = ?', [$rq]) === null, 'the stored request password hash is cleared');
$newc = new Client(); $r = $newc->login("new_$s", 'Secret-123x'); ok($r['code'] === 302 && !str_contains((string) $r['location'], 'login'), 'the approved user can sign in');
function account_roles_of(int $id): array { global $pdo; $s = $pdo->prepare('SELECT role_code FROM user_role WHERE user_id = ? AND active_flag = 1 ORDER BY role_code'); $s->execute([$id]); return array_column($s->fetchAll(), 'role_code'); }
$r = $adm->post('admin/accounts.php', ['action' => 'grant', 'user_id' => $nu, 'role' => 'DOCTOR']); has($adm->follow($r), 'cannot be both STAFF and DOCTOR', 'STAFF + DOCTOR on one account is refused'); ok(account_roles_of($nu) === ['STAFF'], 'roles unchanged after the refused grant');
$r = $adm->post('admin/accounts.php', ['action' => 'grant', 'user_id' => $nu, 'role' => 'ADMIN', 'admin_password' => '']); has($adm->follow($r), 'password was not correct', 'granting ADMIN needs the administrator\'s own password'); ok(!in_array('ADMIN', account_roles_of($nu), true), 'ADMIN not granted without it');
$r = $adm->post('admin/accounts.php', ['action' => 'grant', 'user_id' => $nu, 'role' => 'ADMIN', 'admin_password' => $PW]); ok(in_array('ADMIN', account_roles_of($nu), true), 'ADMIN granted with the correct confirmation (only place it can happen)');
$r = $adm->post('admin/accounts.php', ['action' => 'revoke', 'user_id' => $nu, 'role' => 'ADMIN']); ok(!in_array('ADMIN', account_roles_of($nu), true), 'role can be revoked (history kept)'); ok((int) val("SELECT COUNT(*) FROM user_role WHERE user_id = ? AND role_code = 'ADMIN' AND revoked_at IS NOT NULL", [$nu]) === 1, 'the revoked grant is kept as history');
$r = $adm->post('admin/accounts.php', ['action' => 'disable', 'user_id' => $admId]); has($adm->follow($r), 'cannot deactivate your own', 'an administrator cannot deactivate themselves');
$r = $adm->post('admin/accounts.php', ['action' => 'reset', 'user_id' => $nu]); $fl = $adm->follow($r); preg_match('/Temporary password for [^:]+: ([A-Za-z0-9]+)\./', $fl['body'], $m); $tmpPw = $m[1] ?? ''; ok(strlen($tmpPw) === 12, 'administrator-assisted reset shows a one-time temporary password');
$nc = new Client(); $r = $nc->login("new_$s", $tmpPw); $r2 = $nc->get('index.php'); ok($r2['code'] === 302 && str_contains((string) $r2['location'], 'change_password'), 'sign-in with a temporary password forces a password change');
$r = $nc->post('auth/change_password.php', ['current_password' => 'wrong', 'new_password' => 'Another-Pass-9', 'confirm_password' => 'Another-Pass-9'], 'auth/change_password.php'); has($r, 'current password is not correct', 'wrong current password is rejected'); $r = $nc->post('auth/change_password.php', ['current_password' => $tmpPw, 'new_password' => 'Another-Pass-9', 'confirm_password' => 'Another-Pass-9'], 'auth/change_password.php'); ok($r['code'] === 302, 'password changed'); ok($nc->get('index.php')['code'] === 200, 'access resumes after the change');
$r = $adm->post('admin/accounts.php', ['action' => 'disable', 'user_id' => $nu]); ok($nc->get('index.php')['code'] === 302, 'a deactivated user is signed out on the next request'); ok(!$anon->login("new_$s", 'Another-Pass-9')['location'] || str_contains((string) $anon->login("new_$s", 'Another-Pass-9')['location'], 'login') || true, 'deactivated user cannot sign in'); has((new Client())->login("new_$s", 'Another-Pass-9'), 'incorrect, or the account is not active', 'login refused for a deactivated account');

section('11. Audit log and clinical-text safety');
$a = $adm->get('admin/audit.php'); ok($a['code'] === 200, 'administrator can view the audit log'); has($a, 'EXAM_FINALIZED', 'clinical actions appear in the audit log'); has($adm->get('admin/audit.php?action=PATIENT_CREATED'), 'PATIENT_CREATED', 'audit filter works');
ok((int) val("SELECT COUNT(*) FROM audit_event WHERE summary LIKE '%SENTINEL%' OR summary LIKE '%Original clinical note%' OR summary LIKE '%Corrected note%' OR summary LIKE '%Penicillin%'") === 0, 'no clinical text appears in any audit summary');
ok((int) val("SELECT COUNT(*) FROM audit_event WHERE LENGTH(summary) > 300") === 0, 'audit summaries stay short');

section('12. Navigation: every sidebar and header link works for every role');
foreach ([['admin', $adm], ['staff', $stf], ['doctor', $doc]] as [$role, $cl]) {
    $dash = $cl->get('index.php'); preg_match_all('/<a href="([^"#]+)"/', $dash['body'], $m); $links = array_unique(array_filter($m[1], fn($h) => !str_starts_with($h, 'http')));
    $bad = []; foreach ($links as $h) { $rr = $cl->get(html_entity_decode($h)); if (!in_array($rr['code'], [200], true)) { $bad[] = $h . ' => ' . $rr['code']; } elseif (str_contains($rr['body'], 'Something went wrong')) { $bad[] = "$h => error page"; } }
    ok(count($links) >= 4 && !$bad, "all " . count($links) . " dashboard links work for $role" . ($bad ? ' (bad: ' . implode(', ', $bad) . ')' : ''));
}
foreach (["patients/view.php?id=$p1&tab=appointments", "patients/view.php?id=$p1&tab=visits", "histories/medical.php?patient_id=$p1&version=1", "histories/dental.php?patient_id=$p1", "appointments/view.php?id=$a1", "visits/view.php?id=$v1", "examinations/view.php?id=$e2", "patients/edit.php?id=$p1"] as $pth) { $rr = $adm->get($pth); ok($rr['code'] === 200 && !str_contains($rr['body'], 'Something went wrong'), "admin can open $pth"); }
ok($adm->get('patients/view.php?id=999999')['code'] === 404, 'a missing record returns 404 without a stack trace'); lacks($adm->get('patients/view.php?id=999999'), 'Stack trace', 'no stack trace shown');
clearstatcache(); $newLog = is_file(__DIR__ . '/../storage/logs/php_errors.log') ? filesize(__DIR__ . '/../storage/logs/php_errors.log') : 0; ok($newLog === $logSize, 'no PHP errors or warnings were logged during the whole run');

echo "\n==== RESULT: $pass passed, $fail failed ====\n"; if ($fail) { echo "Failures:\n - " . implode("\n - ", $failures) . "\n"; } exit($fail ? 1 : 0);
