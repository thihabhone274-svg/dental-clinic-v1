<?php
declare(strict_types=1);
function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_path(): string {
    $b = cfg('base_path');
    if ($b !== null) { return rtrim((string) $b, '/'); }
    $root = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $doc = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    return ($doc !== '' && stripos($root, $doc) === 0) ? rtrim(substr($root, strlen($doc)), '/') : '';   // case-insensitive: Windows drive letters differ in case
}
function url(string $path = ''): string { return base_path() . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function request_id(): string { static $id = null; return $id ??= bin2hex(random_bytes(8)); }
function client_ip(): string { return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $sent = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        audit('CSRF_REJECTED', null, null, 'DENIED');
        abort(400, 'Form expired', 'Your form could not be verified (it may have expired). Go back, reload the page and try again. Nothing was saved.');
    }
}
/** Every state-changing page starts with this: POST only, valid CSRF token. */
function require_post(): void {
    if (!is_post()) { abort(405, 'Method not allowed', 'This action must be submitted from a form.'); }
    csrf_check();
}
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['t' => $type, 'm' => $msg]; }
function abort(int $code, string $title, string $message): never {
    http_response_code($code);
    if (function_exists('current_user') && current_user()) {
        page_start($title, ['nav' => '']);
        echo '<div class="empty-state"><h2>' . e($title) . '</h2><p>' . e($message) . '</p><p><a class="btn btn-secondary" href="' . e(url('index.php')) . '">Back to dashboard</a></p></div>';
        page_end();
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title><body style="font-family:system-ui;max-width:560px;margin:15vh auto;padding:0 16px"><h1>' . e($title) . '</h1><p>' . e($message) . '</p><p><a href="' . e(url('login.php')) . '">Sign in</a></p></body>';
    }
    exit;
}
function get_int(string $k): int { return (int) preg_replace('/\D/', '', (string) ($_GET[$k] ?? '0')); }
function post_int(string $k): int { return (int) preg_replace('/\D/', '', (string) ($_POST[$k] ?? '0')); }
function fmt_dt(?string $s): string { return $s ? date('Y-m-d H:i', strtotime($s)) : '—'; }
function fmt_date(?string $s): string { return $s ? date('Y-m-d', strtotime($s)) : '—'; }
function label(string $code): string { return ucfirst(strtolower(str_replace('_', ' ', $code))); }
function age_from(?string $dob): string {
    if (!$dob) { return ''; }
    return (string) (new DateTime($dob))->diff(new DateTime('today'))->y;
}
/** @return array{0:int,1:int,2:int} [page, pages, offset] */
function paginate(int $total, int $per): array {
    $pages = max(1, (int) ceil($total / $per));
    $page = min(max(1, get_int('page')), $pages);
    return [$page, $pages, ($page - 1) * $per];
}
function pager(int $page, int $pages, array $query = []): string {
    if ($pages <= 1) { return ''; }
    $link = fn(int $p, string $t, bool $dis = false) => $dis ? '<span class="page-link disabled">' . $t . '</span>'
        : '<a class="page-link" href="?' . e(http_build_query(array_merge($query, ['page' => $p]))) . '">' . $t . '</a>';
    return '<nav class="pager" aria-label="Pagination">' . $link($page - 1, '&larr; Previous', $page <= 1)
        . '<span class="page-info">Page ' . $page . ' of ' . $pages . '</span>' . $link($page + 1, 'Next &rarr;', $page >= $pages) . '</nav>';
}
function like_escape(string $s): string { return addcslashes($s, '\\%_'); }
function next_code(string $prefix, int $id, int $pad = 6): string { return $prefix . '-' . date('Y') . '-' . str_pad((string) $id, $pad, '0', STR_PAD_LEFT); }
