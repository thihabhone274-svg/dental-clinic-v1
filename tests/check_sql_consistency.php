<?php
/**
 * Checks that the application's SQL matches the schema. Every STATIC SQL string in the PHP code (SELECT / INSERT / UPDATE)
 * is PREPARED against the real database; the server verifies each table and column name. Dynamic SQL (built with variables)
 * is checked separately by running the application tests. Run against a test database imported from database/dental_clinic_setup.sql.
 * SAFETY: refuses to run unless the database name ends with _test. Nothing is executed or changed (prepare only).
 */
$cfgFile = getenv('DENTAL_CONFIG') ?: __DIR__ . '/../config/database.php'; $c = require $cfgFile;
if (!preg_match('/_test$/', $c['name'])) { fwrite(STDERR, "Refusing to run: database must end with _test.\n"); exit(2); }
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$root = dirname(__DIR__); $checked = 0; $bad = 0; $dynamic = 0; $seen = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'php' || str_contains($f->getPathname(), '/tests/')) { continue; }
    $toks = token_get_all(file_get_contents($f->getPathname()));
    foreach ($toks as $i => $t) {
        if (!is_array($t)) { continue; }
        if ($t[0] === T_ENCAPSED_AND_WHITESPACE && preg_match('/^\s*(SELECT|INSERT|UPDATE)\b/i', $t[1])) { $dynamic++; continue; }
        if ($t[0] !== T_CONSTANT_ENCAPSED_STRING) { continue; }
        $sql = stripcslashes(substr($t[1], 1, -1));
        if (!preg_match('/^\s*(SELECT|INSERT INTO|UPDATE)\b/i', $sql) || isset($seen[$sql])) { continue; }
        $j = $i + 1; while (isset($toks[$j]) && is_array($toks[$j]) && in_array($toks[$j][0], [T_WHITESPACE, T_COMMENT], true)) { $j++; }
        if (isset($toks[$j]) && $toks[$j] === '.') { $dynamic++; continue; }   // a fragment that is concatenated with other parts at run time
        $seen[$sql] = true;
        try { $pdo->prepare($sql); $checked++; }
        catch (PDOException $e) { $bad++; echo "MISMATCH in " . substr($f->getPathname(), strlen($root) + 1) . ":\n  $sql\n  " . $e->getMessage() . "\n"; }
    }
}
echo "$checked static SQL statements prepared against the schema, $bad mismatches ($dynamic dynamic fragments skipped; those are exercised by the application tests).\n"; exit($bad ? 1 : 0);
