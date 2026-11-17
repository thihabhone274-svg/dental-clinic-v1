<?php
declare(strict_types=1);
// Loaded first by every page: configuration, error handling, session, security headers, shared includes.
define('APP_ROOT', dirname(__DIR__));
$GLOBALS['APP_CONFIG'] = require APP_ROOT . '/config/app.php';
$GLOBALS['CLINIC'] = require APP_ROOT . '/config/clinic_options.php';
function cfg(string $key, $default = null) { return $GLOBALS['APP_CONFIG'][$key] ?? $default; }
function clinic(string $key) { return $GLOBALS['CLINIC'][$key] ?? []; }

date_default_timezone_set((string) cfg('timezone', 'UTC'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(APP_ROOT . '/storage/logs')) { @mkdir(APP_ROOT . '/storage/logs', 0775, true); }
ini_set('error_log', APP_ROOT . '/storage/logs/php_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/patient_fields.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/workflow.php';
foreach (['patients', 'histories', 'appointments', 'visits', 'exams', 'accounts'] as $m) { require_once __DIR__ . "/$m.php"; }

set_exception_handler(function (Throwable $t): void {
    $ref = bin2hex(random_bytes(4));
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Error: " . $t->getMessage() . "\n"); exit(1); }
    error_log("[$ref] " . get_class($t) . ': ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:system-ui;max-width:560px;margin:15vh auto;padding:0 16px">'
       . '<h1>Something went wrong</h1><p>The request could not be completed and nothing was saved. Please try again. '
       . 'If it keeps happening, give this reference to the administrator: <b>' . $ref . '</b>.</p></body>';
});

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'");
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name((string) cfg('session_name', 'DENTALSESSID'));
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
    session_start();
    $idle = (int) cfg('session_idle_minutes', 120) * 60;
    if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > $idle) {
        $_SESSION = []; session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}
