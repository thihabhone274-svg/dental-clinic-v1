<?php
// Checks your installation. Run:  C:\xampp\php\php.exe scripts\check_setup.php
// It changes nothing. It reports PHP version, extensions, configuration, database connection, schema and timezone.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('APP_ROOT', dirname(__DIR__));
$ok = true; $line = function (bool $good, string $msg) use (&$ok) { echo ($good ? '[ OK ] ' : '[FAIL] ') . $msg . "\n"; if (!$good) { $ok = false; } };
echo "Dental Clinic Management System: setup check\n\n";
$line(version_compare(PHP_VERSION, '8.1.0', '>='), 'PHP version ' . PHP_VERSION . ' (8.1 or newer required; developed for 8.2)');
foreach (['pdo_mysql', 'mbstring'] as $ext) { $line(extension_loaded($ext), "PHP extension $ext" . (extension_loaded($ext) ? '' : ' is NOT enabled: enable it in php.ini (remove the leading ; before extension=' . $ext . ') and restart Apache')); }
$app = require APP_ROOT . '/config/app.php';
$line(in_array($app['timezone'], DateTimeZone::listIdentifiers(), true), 'Timezone setting "' . $app['timezone'] . '" is valid (change it in config/app.php to your clinic\'s timezone)');
$cfgFile = getenv('DENTAL_CONFIG') ?: APP_ROOT . '/config/database.php';
$line(is_file($cfgFile), 'Database configuration file exists (' . basename($cfgFile) . ')' . (is_file($cfgFile) ? '' : ': copy config/database.example.php to config/database.php and edit it'));
if (is_file($cfgFile) && extension_loaded('pdo_mysql')) {
    $c = require $cfgFile;
    echo strtolower((string) $c['user']) === 'root' ? "[WARN] The application connects as root. Create the dental_app user with database/optional_create_app_user.sql and use it in config/database.php.\n" : "[ OK ] Application does not connect as root\n";
    try {
        $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) $c['port'], $c['name']), $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $line(true, 'Connected to database "' . $c['name'] . '" (server ' . $pdo->query('SELECT VERSION()')->fetchColumn() . ')');
        $v = $pdo->query('SELECT VERSION()')->fetchColumn();
        $line((bool) preg_match('/mariadb/i', (string) $v) ? version_compare((string) preg_replace('/^(\d+\.\d+\.\d+).*/', '$1', preg_replace('/^5\.5\.5-/', '', (string) $v)), '10.4.0', '>=') : true, 'Database version is supported (MariaDB 10.4 or newer; CHECK constraints are enforced)');
        $tables = ['role', 'user_account', 'user_role', 'doctor', 'account_request', 'patient', 'medical_history_version', 'allergy_item', 'dental_history_version', 'appointment', 'appointment_change', 'clinical_visit', 'clinical_examination', 'clinical_examination_extra', 'audit_event', 'schema_version'];
        $have = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0); $missing = array_diff($tables, $have);
        $line(!$missing, 'All ' . count($tables) . ' application tables exist' . ($missing ? ' (missing: ' . implode(', ', $missing) . '): import database/dental_clinic_setup.sql' : ''));
        if (!$missing) {
            $line((int) $pdo->query('SELECT COUNT(*) FROM role')->fetchColumn() === 3, 'Roles ADMIN, STAFF, DOCTOR are present');
            $line((int) $pdo->query("SELECT COUNT(*) FROM user_role WHERE role_code = 'ADMIN' AND active_flag = 1")->fetchColumn() > 0, 'An administrator exists' . ((int) $pdo->query("SELECT COUNT(*) FROM user_role WHERE role_code = 'ADMIN' AND active_flag = 1")->fetchColumn() > 0 ? '' : ': run scripts/create_first_admin.php'));
            $bad = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT user_id FROM user_role WHERE active_flag = 1 AND role_code IN ('STAFF','DOCTOR') GROUP BY user_id HAVING COUNT(DISTINCT role_code) = 2) x")->fetchColumn();
            $line($bad === 0, 'No account holds both STAFF and DOCTOR' . ($bad ? " ($bad found: fix on Accounts and roles)" : ''));
        }
    } catch (PDOException $e) { $line(false, 'Database connection failed: ' . $e->getMessage()); }
}
echo "\n" . ($ok ? "Everything looks fine.\n" : "Some checks failed. Fix the [FAIL] lines above, then run this check again.\n"); exit($ok ? 0 : 1);
