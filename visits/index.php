<?php
require __DIR__ . '/../includes/bootstrap.php';
require_can('visit.view');
$e = [];
$f = ['status' => in_array((string) ($_GET['status'] ?? ''), ['OPEN', 'CLOSED', 'CANCELLED'], true) ? (string) $_GET['status'] : '', 'from' => v_date($_GET, 'from', $e, 'From') ?? '', 'to' => v_date($_GET, 'to', $e, 'To') ?? ''];
$per = (int) cfg('page_size', 20);
[, $total] = visit_list($f, 1, 0); [$page, $pages, $off] = paginate($total, $per); [$rows] = visit_list($f, $per, $off);
$docOnly = !has_role('ADMIN') && !has_role('STAFF');
page_start($docOnly ? 'My visits' : 'Visits', ['nav' => 'visits', 'subtitle' => $docOnly ? 'Visits where you are the responsible doctor. Check in patients first.' : 'Check in patients first.',
    'actions' => can('appointment.write') ? '<a class="btn btn-primary" href="' . e(url('visits/open.php')) . '">Check in patient</a>' : '']);
?>
<form method="get" class="filters"><?= f_select('status', 'Status', ['OPEN' => 'Open', 'CLOSED' => 'Closed', 'CANCELLED' => 'Cancelled'], $f, [], ['placeholder' => 'All']) ?>
  <?= f_text('from', 'From', $f, $e, ['type' => 'date']) ?><?= f_text('to', 'To', $f, $e, ['type' => 'date']) ?><div class="field"><label>&nbsp;</label><button class="btn btn-primary">Filter</button> <a class="btn btn-secondary" href="?">Clear</a></div></form>
<?php if (!$rows): ?><div class="empty-state"><h2>No visits found</h2><p>Check in patient from an appointment, or register a walk-in.</p></div><?php else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Visit</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Opened</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><a href="<?= e(url('visits/view.php?id=' . $r['visit_id'])) ?>"><?= e($r['visit_no']) ?></a></td><td><?= e($r['patient_name']) ?> <span class="muted"><?= e($r['patient_code']) ?></span></td><td><?= e($r['doctor_name']) ?></td>
<td><?= e(label($r['visit_type'])) ?></td><td><?= e(fmt_dt($r['visited_at'])) ?></td><td><?= status_badge($r['status']) ?><?= $r['drafts'] ? ' ' . badge('Draft exam', 'warn') : '' ?></td><td><a href="<?= e(url('visits/view.php?id=' . $r['visit_id'])) ?>">Open</a></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($page, $pages, array_filter($f)) ?><?php endif; ?>
<?php page_end();
