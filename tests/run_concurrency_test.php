<?php
/**
 * Concurrency check: many staff members try to book the SAME doctor at the SAME time at once.
 * Exactly one booking must succeed. Needs a server that handles requests in parallel (on Linux/Mac:
 * PHP_CLI_SERVER_WORKERS=6 php -S ...; Apache in XAMPP is parallel by default). SAFETY: database name must end with _test.
 * Usage: php tests/run_concurrency_test.php http://127.0.0.1:8099
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/'); $c = require (getenv('DENTAL_CONFIG') ?: __DIR__ . '/../config/database.php');
if (!preg_match('/_test$/', $c['name'])) { fwrite(STDERR, "Refusing: database must end with _test.\n"); exit(2); }
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$s = substr(bin2hex(random_bytes(3)), 0, 6); $N = 8; $pw = 'Test-Pass-123';
$pdo->prepare('INSERT INTO user_account (username, username_key, display_name, password_hash) VALUES (?,?,?,?)')->execute(["cstf_$s", "cstf_$s", 'Conc Staff', password_hash($pw, PASSWORD_DEFAULT)]); $stf = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO user_role (user_id, role_code) VALUES (?, 'STAFF')")->execute([$stf]);
$pdo->prepare('INSERT INTO user_account (username, username_key, display_name, password_hash) VALUES (?,?,?,?)')->execute(["cdoc_$s", "cdoc_$s", 'Conc Doctor', password_hash($pw, PASSWORD_DEFAULT)]); $du = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO user_role (user_id, role_code) VALUES (?, 'DOCTOR')")->execute([$du]); $pdo->prepare('INSERT INTO doctor (user_id, doctor_code, full_name) VALUES (?,?,?)')->execute([$du, "CD$s", 'Conc Doctor']); $doc = (int) $pdo->lastInsertId();
$pids = []; for ($i = 0; $i < $N; $i++) { $pdo->prepare('INSERT INTO patient (patient_code, full_name, name_key, registration_date, created_by) VALUES (?,?,?,?,?)')->execute(["CP$s$i", "Conc Patient $i", "conc patient $i", date('Y-m-d'), $stf]); $pids[] = (int) $pdo->lastInsertId(); }
$src = getenv('TEST_SOURCE_IP') ?: ('127.' . random_int(2, 250) . '.' . random_int(1, 250) . '.' . random_int(2, 250)); $jar = tempnam(sys_get_temp_dir(), 'j'); $call = function ($path, $post = null) use ($base, $jar) { $ch = curl_init("$base/$path"); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_INTERFACE => $GLOBALS['src']]); if ($post) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); } $o = curl_exec($ch); curl_close($ch); return (string) $o; };
$tok = function ($p) use ($call) { preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $call($p), $m); return $m[1] ?? ''; };
$call('login.php', ['username' => "cstf_$s", 'password' => $pw, 'csrf_token' => $tok('login.php')]); $page = $call('index.php'); if (!str_contains($page, 'Dashboard')) { echo "FAIL: the test user could not sign in (is the login throttle active? wait 5 minutes).\n"; exit(1); } $csrf = $tok('index.php'); $day = date('Y-m-d', strtotime('+5 day'));
$mh = curl_multi_init(); $hs = [];
foreach ($pids as $pid) { $ch = curl_init("$base/appointments/form.php"); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_INTERFACE => $src, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => $csrf, 'patient_id' => $pid, 'doctor_id' => $doc, 'appt_date' => $day, 'start_time' => '09:00', 'end_time' => '09:30'])]); curl_multi_add_handle($mh, $ch); $hs[] = $ch; }
do { curl_multi_exec($mh, $run); curl_multi_select($mh, 0.2); } while ($run > 0);
$made = (int) $pdo->query("SELECT COUNT(*) FROM appointment WHERE doctor_id = $doc AND status = 'SCHEDULED'")->fetchColumn();
echo "$N simultaneous bookings of the same doctor and time: $made saved.\n"; echo $made === 1 ? "PASS: exactly one booking succeeded.\n" : "FAIL: expected exactly 1.\n"; exit($made === 1 ? 0 : 1);
