<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $file = getenv('DENTAL_CONFIG') ?: APP_ROOT . '/config/database.php';
    if (!is_file($file)) {
        throw new RuntimeException('Database configuration is missing: copy config/database.example.php to config/database.php.');
    }
    $c = require $file;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) $c['port'], $c['name']);
    $pdo = new PDO($dsn, $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Keep the database session in the same timezone as PHP so NOW(), CURDATE() and defaults agree with the app.
    $offset = (new DateTime('now', new DateTimeZone((string) cfg('timezone', 'UTC'))))->format('P');
    $pdo->exec("SET time_zone = '" . $offset . "', sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}
/** Run $fn inside a transaction; commit on success, roll back and rethrow on any exception. */
function tx(callable $fn) {
    $pdo = db();
    $pdo->beginTransaction();
    try { $r = $fn($pdo); $pdo->commit(); return $r; }
    catch (Throwable $t) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $t; }
}
function q(string $sql, array $params = []): PDOStatement { $s = db()->prepare($sql); $s->execute($params); return $s; }
function q_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function q_one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function q_val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
