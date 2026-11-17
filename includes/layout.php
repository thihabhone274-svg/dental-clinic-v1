<?php
declare(strict_types=1);
function page_start(string $title, array $o = []): void {
    $u = current_user();
    $nav = (string) ($o['nav'] ?? '');
    $items = [['dashboard', 'Overview', 'index.php', true], ['patients', 'Patients', 'patients/index.php', can('patient.view')],
        ['appointments', 'Appointments', 'appointments/index.php', can('appointment.view')], ['reception', 'Reception & queue', 'reception/index.php', true], ['visits', 'Clinical visits', 'visits/index.php', can('visit.view')]];
    $admin = [['requests', 'Account requests', 'admin/requests.php', can('admin.requests')], ['accounts', 'Accounts and roles', 'admin/accounts.php', can('admin.accounts')],
        ['audit', 'Audit log', 'admin/audit.php', can('admin.audit')], ['settings', 'Clinic settings', 'admin/settings.php', can('admin.accounts')]];
    $pending = ($u && can('admin.requests')) ? (int) q_val("SELECT COUNT(*) FROM account_request WHERE request_status = 'PENDING'") : 0;
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . ' · ' . e(cfg('app_name')) . '</title><link rel="stylesheet" href="' . e(url('assets/css/app.css')) . '"><link rel="stylesheet" href="' . e(url('assets/css/core.css')) . '"></head><body class="' . ($u ? 'app' : 'plain') . '">';
    if ($u) {
        echo '<a class="skip" href="#main">Skip to content</a><header class="topbar"><button class="menu-toggle" type="button" aria-label="Menu" data-menu>&#9776;</button>'
           . '<a class="brand" href="' . e(url('index.php')) . '"><span class="brand-mark" aria-hidden="true"></span>Ngwe La Yaung <span class="brand-version">V2</span></a><span class="grow"></span>'
           . '<span class="who">' . e($u['display_name']) . ' ' . implode(' ', array_map(fn($r) => badge(label($r), $r === 'ADMIN' ? 'ok' : 'info'), $u['roles'])) . '</span>'
           . '<a class="toplink" href="' . e(url('auth/change_password.php')) . '">Change password</a>'
           . '<form method="post" action="' . e(url('logout.php')) . '" class="inline">' . csrf_field() . '<button class="toplink" type="submit">Sign out</button></form></header>'
           . '<div class="shell"><nav class="side" aria-label="Main" id="side"><div class="side-head">Clinic workspace</div>';
        foreach ($items as [$k, $t, $href, $show]) { if ($show) { echo '<a href="' . e(url($href)) . '" class="' . ($nav === $k ? 'on' : '') . '">' . '<span class="nav-label">' . ui_icon($k) . e($t) . '</span></a>'; } }
        if (can('admin.requests') || can('admin.accounts') || can('admin.audit')) {
            echo '<div class="side-head">Administration</div>';
            foreach ($admin as [$k, $t, $href, $show]) {
                if ($show) { echo '<a href="' . e(url($href)) . '" class="' . ($nav === $k ? 'on' : '') . '">' . '<span class="nav-label">' . ui_icon($k) . e($t) . '</span>' . ($k === 'requests' && $pending ? ' <span class="count">' . $pending . '</span>' : '') . '</a>'; }
            }
        }
        echo '<div class="side-footer"><span class="live-dot"></span> Local clinic workspace<small>V2 Core · Clinical operations</small></div></nav><main id="main" class="main">';
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
       . '<script src="' . e(url('assets/js/app.js')) . '"></script><script src="' . e(url('assets/js/patient-form.js')) . '"></script></body></html>';
}

function ui_icon(string $name): string {
    $paths=[
        'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'patients'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 5v2"/>',
        'appointments'=>'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18M8 15h2m4 0h2m-8 3h2"/>',
        'reception'=>'<path d="M4 7h16M4 12h12M4 17h8"/><circle cx="19" cy="17" r="3"/>',
        'visits'=>'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v4H9zM9 13h6m-3-3v6"/>',
        'check'=>'<circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/>',
        'settings'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2"/>',
        'audit'=>'<path d="M5 3h14v18H5zM8 7h8m-8 5h8m-8 5h5"/>'
    ];
    $name=['calendar'=>'appointments','queue'=>'reception','accounts'=>'patients','requests'=>'patients'][$name]??$name;
    return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['dashboard']).'</svg>';
}
