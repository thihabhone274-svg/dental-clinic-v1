<?php
// Checks PHP syntax of every PHP file in the project. Run:  php tests/lint_all.php
$root = dirname(__DIR__); $bad = 0; $n = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'php') { continue; }
    $n++; $out = []; $code = 0; exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($f->getPathname()) . ' 2>&1', $out, $code);
    if ($code !== 0) { $bad++; echo "SYNTAX ERROR: " . substr($f->getPathname(), strlen($root) + 1) . "\n" . implode("\n", $out) . "\n"; }
}
echo "$n PHP files checked, $bad with syntax errors.\n"; exit($bad ? 1 : 0);
