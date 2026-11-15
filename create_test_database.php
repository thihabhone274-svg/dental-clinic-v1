<?php
// Creates a fresh, EMPTY test database (name must end with _test) from database/dental_clinic_setup.sql.
// It never touches an existing database: if the name is already taken it stops.
// Run:  set DENTAL_CONFIG=C:\xampp\htdocs\dental_clinic\tests\config.test.php   then   C:\xampp\php\php.exe scripts\create_test_database.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$cfgFile = getenv('DENTAL_CONFIG') ?: __DIR__ . '/../tests/config.test.php';
if (!is_file($cfgFile)) { exit("Test configuration not found ($cfgFile). Copy tests/config.test.example.php to tests/config.test.php.\n"); }
$c = require $cfgFile;
if (!preg_match('/^[A-Za-z0-9_]+_test$/', $c['name'])) { exit("Refusing: the database name must end with _test.\n"); }
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$st = $pdo->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'); $st->execute([$c['name']]);
if ($st->fetchColumn()) { exit("Database {$c['name']} already exists. Nothing was changed. Drop it yourself in phpMyAdmin if you want a fresh one.\n"); }
$sql = str_replace('`dental_clinic`', '`' . $c['name'] . '`', (string) file_get_contents(__DIR__ . '/../database/dental_clinic_setup.sql'));
$sql = preg_replace('/^\s*--.*$/m', '', $sql); $n = 0;
foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) { $pdo->exec($stmt); $n++; }
echo "Created {$c['name']} ($n statements). You can now start the test server and run tests/run_tests.php (see docs/TESTING.md).\n";
