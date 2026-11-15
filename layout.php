<?php
declare(strict_types=1);
function page_start(string $title, array $o = []): void {
    $u = current_user();
    $nav = (string) ($o['nav'] ?? '');
    $items = [['dashboard', 'Dashboard', 'index.php', true], ['patients', 'Patients', 'patients/index.php', can('patient.view')],
        ['appointments', 'Appointments', 'appointments/index.php', can('appointment.view')], ['visits', 'Visits', 'visits/index.php', can('visit.view')]];
    $admin = [['requests', 'Account requests', 'admin/requests.php', can('admin.requests')], ['accounts', 'Accounts and roles', 'admin/accounts.php', can('admin.accounts')],
        ['audit', 'Audit log', 'admin/audit.php', can('admin.audit')]];
    $pending = ($u && can('admin.requests')) ? (int) q_val("SELECT COUNT(*) FROM account_request WHERE request_status = 'PENDING'") : 0;
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . ' · ' . e(cfg('app_name')) . '</title><link rel="stylesheet" href="' . e(url('assets/css/app.css')) . '"></head><body class="' . ($u ? 'app' : 'plain') . '">';
    if ($u) {
        echo '<a class="skip" href="#main">Skip to content</a><header class="topbar"><button class="menu-toggle" type="button" aria-label="Menu" data-menu>&#9776;</button>'
           . '<a class="brand" href="' . e(url('index.php')) . '"><span class="brand-mark" aria-hidden="true"></span>Dental Clinic</a><span class="grow"></span>'
           . '<span class="who">' . e($u['display_name']) . ' ' . implode(' ', array_map(fn($r) => badge(label($r), $r === 'ADMIN' ? 'ok' : 'info'), $u['roles'])) . '</span>'
           . '<a class="toplink" href="' . e(url('auth/change_password.php')) . '">Change password</a>'
           . '<form method="post" action="' . e(url('logout.php')) . '" class="inline">' . csrf_field() . '<button class="toplink" type="submit">Sign out</button></form></header>'
           . '<div class="shell"><nav class="side" aria-label="Main" id="side">';
        foreach ($items as [$k, $t, $href, $show]) { if ($show) { echo '<a href="' . e(url($href)) . '" class="' . ($nav === $k ? 'on' : '') . '">' . e($t) . '</a>'; } }
        if (can('admin.requests') || can('admin.accounts') || can('admin.audit')) {
            echo '<div class="side-head">Administration</div>';
            foreach ($admin as [$k, $t, $href, $show]) {
                if ($show) { echo '<a href="' . e(url($href)) . '" class="' . ($nav === $k ? 'on' : '') . '">' . e($t) . ($k === 'requests' && $pending ? ' <span class="count">' . $pending . '</span>' : '') . '</a>'; }
            }
        }
        echo '</nav><main id="main" class="main">';
        if (!empty($o['crumbs'])) {
            echo '<nav class="crumbs" aria-label="Breadcrumb">';
            foreach ($o['crumbs'] as [$t, $href]) { echo $href ? '<a href="' . e(url($href)) . '">' . e($t) . '</a><span>/</span>' : ''; }
            echo '<span class="here">' . e($title) . '</span></nav>';
        }
        echo '<div class="page-head"><div><h1>' . e($title) . '</h1>' . (!empty($o['subtitle']) ? '<p class="sub">' . e($o['subtitle']) . '</p>' : '') . '</div>'
           . (!empty($o['actions']) ? '<div class="actions">' . $o['actions'] . '</div>' : '') . '</div>';
    } else {
        echo '<main class="auth-wrap">';
    }
    foreach (($_SESSION['flash'] ?? []) as $f) { echo '<div class="alert alert-' . e($f['t']) . '" role="status">' . e($f['m']) . '</div>'; }
    $_SESSION['flash'] = [];
}
function page_end(): void {
    $u = current_user();
    echo ($u ? '</main></div>' : '</main>')
       . '<dialog id="confirm-dialog"><form method="dialog"><h2>Please confirm</h2><p id="confirm-text"></p><div class="dlg-actions">'
       . '<button value="cancel" class="btn btn-secondary">Cancel</button><button value="ok" class="btn btn-primary" id="confirm-ok">Confirm</button></div></form></dialog>'
       . '<script src="' . e(url('assets/js/app.js')) . '"></script></body></html>';
}
