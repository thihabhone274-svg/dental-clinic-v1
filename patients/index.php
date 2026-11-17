<?php
require __DIR__ . '/../includes/bootstrap.php';
require_can('patient.view');
$f = ['q' => trim((string) ($_GET['q'] ?? '')), 'status' => in_array((string) ($_GET['status'] ?? 'active'), ['active', 'archived', 'all'], true) ? (string) ($_GET['status'] ?? 'active') : 'active',
      'sort' => (string) ($_GET['sort'] ?? 'name')];
$per = (int) cfg('page_size', 20);
[, $total] = patient_search($f, 1, 0);
[$page, $pages, $off] = paginate($total, $per);
[$rows] = patient_search($f, $per, $off);
$doctorOnly = !has_role('ADMIN') && !has_role('STAFF') && cfg('doctor_scope') !== 'all';
page_start($doctorOnly ? 'My patients' : 'Patient directory', ['nav' => 'patients',
    'subtitle' => $doctorOnly ? 'Only patients in your active care are listed: an appointment today or later, or an open visit with you.' : 'Search by registration number, name, telephone, NRC or exact date of birth (YYYY-MM-DD).',
    'actions' => can('patient.write') ? '<a class="btn btn-primary" href="' . e(url('patients/create.php')) . '">Register patient</a>' : '']);
?>
<form method="get" class="filters" role="search">
  <?= f_text('q', 'Search', $f, [], ['maxlength' => 100]) ?>
  <?= f_select('status', 'Show', ['active' => 'Active', 'archived' => 'Archived', 'all' => 'All'], $f, []) ?>
  <?= f_select('sort', 'Sort by', ['name' => 'Name', 'code' => 'Registration number', 'recent' => 'Newest first'], $f, []) ?>
  <div class="field"><label>&nbsp;</label><button class="btn btn-primary">Search</button> <a class="btn btn-secondary" href="?">Clear</a></div>
</form>
<?php if (!$rows): ?>
  <div class="empty-state"><h2>No matching patients</h2><p><?= $doctorOnly ? 'Doctors see only patients in active care. Ask staff to book an appointment or open a visit.' : 'Check the spelling, or register a new patient.' ?></p>
  <?php if (can('patient.write')): ?><a class="btn btn-primary" href="<?= e(url('patients/create.php')) ?>">Register patient</a><?php endif; ?></div>
<?php else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Reg. no.</th><th>Name</th><th>Date of birth</th><th>Telephone</th><th>Last visit</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><a href="<?= e(url('patients/view.php?id=' . $r['patient_id'])) ?>"><?= e($r['patient_code']) ?></a></td><td><a href="<?= e(url('patients/view.php?id=' . $r['patient_id'])) ?>"><?= e($r['full_name']) ?></a></td>
<td><?= e(fmt_date($r['date_of_birth'])) ?></td><td><?= e($r['phone'] ?? '') ?></td><td><?= e(fmt_date($r['last_visit'])) ?></td><td><?= status_badge($r['archived_at'] ? 'ARCHIVED' : 'ACTIVE') ?></td></tr><?php endforeach; ?></tbody></table></div>
<p class="muted"><?= (int) $total ?> patient(s)</p><?= pager($page, $pages, array_filter($f, fn($x) => $x !== '')) ?>
<?php endif; ?>
<?php page_end();
