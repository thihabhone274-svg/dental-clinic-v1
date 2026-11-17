<?php
require __DIR__ . '/../includes/bootstrap.php';
require_can('appointment.view');
$e = [];
$from = v_date($_GET, 'from', $e, 'From') ?? date('Y-m-d'); $to = v_date($_GET, 'to', $e, 'To') ?? date('Y-m-d', strtotime($from . ' +6 day'));
if ($to < $from) { $to = $from; }
$f = ['from' => $from, 'to' => $to, 'doctor_id' => get_int('doctor_id'), 'status' => in_array((string) ($_GET['status'] ?? ''), ['SCHEDULED', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'], true) ? (string) $_GET['status'] : ''];
$rows = appt_list($f);
$days = []; foreach ($rows as $r) { $days[substr($r['start_at'], 0, 10)][] = $r; }
$span = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1);
$isDoctorOnly = !has_role('ADMIN') && !has_role('STAFF');
$nav = fn($a, $b) => '?' . http_build_query(array_filter(['from' => $a, 'to' => $b, 'doctor_id' => $f['doctor_id'] ?: null, 'status' => $f['status'] ?: null]));
page_start($isDoctorOnly ? 'My schedule' : 'Appointments', ['nav' => 'appointments', 'subtitle' => $isDoctorOnly ? 'Your bookings only.' : 'All doctors. Grouped by day.',
    'actions' => can('appointment.write') ? '<a class="btn btn-primary" href="' . e(url('appointments/form.php')) . '">Book appointment</a>' : '']);
?>
<form method="get" class="filters">
  <?= f_text('from', 'From', $f, [], ['type' => 'date']) ?><?= f_text('to', 'To', $f, [], ['type' => 'date']) ?>
  <?php if (!$isDoctorOnly): ?><?= f_select('doctor_id', 'Doctor', doctor_options(), $f, [], ['placeholder' => 'All doctors']) ?><?php endif; ?>
  <?= f_select('status', 'Status', ['SCHEDULED' => 'Scheduled', 'CONFIRMED' => 'Confirmed', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled', 'NO_SHOW' => 'No-show'], $f, [], ['placeholder' => 'All statuses']) ?>
  <div class="field"><label>&nbsp;</label><button class="btn btn-primary">Show</button></div></form>
<div class="date-nav"><a class="btn btn-secondary btn-sm" href="<?= e($nav(date('Y-m-d', strtotime($from . " -$span day")), date('Y-m-d', strtotime($to . " -$span day")))) ?>">&larr; Earlier</a>
  <a class="btn btn-secondary btn-sm" href="<?= e($nav(date('Y-m-d'), date('Y-m-d'))) ?>">Today</a><a class="btn btn-secondary btn-sm" href="<?= e($nav(date('Y-m-d'), date('Y-m-d', strtotime('+6 day')))) ?>">Next 7 days</a>
  <a class="btn btn-secondary btn-sm" href="<?= e($nav(date('Y-m-d', strtotime($from . " +$span day")), date('Y-m-d', strtotime($to . " +$span day")))) ?>">Later &rarr;</a>
  <span class="muted"><?= e($from) ?> to <?= e($to) ?> · <?= count($rows) ?> appointment(s)</span></div>
<?php
for ($t = strtotime($from), $n = 0; $t <= strtotime($to) && $n < 62; $t = strtotime('+1 day', $t), $n++):
  $day = date('Y-m-d', $t); $list = $days[$day] ?? [];
  if (!$list && $span > 14) { continue; } ?>
<section class="day <?= $day === date('Y-m-d') ? 'today' : '' ?>"><h3><?= e(date('l, j F Y', $t)) ?><?= $day === date('Y-m-d') ? ' ' . badge('Today', 'info') : '' ?></h3>
<?php if (!$list): ?><p class="muted">No appointments.</p><?php else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Time</th><th>No.</th><th>Patient</th><th>Doctor</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($list as $a): $open = in_array($a['status'], ['SCHEDULED', 'CONFIRMED'], true); ?>
<tr><td class="nowrap"><?= e(date('H:i', strtotime($a['start_at']))) ?></td><td class="nowrap"><?= e($a['appointment_no']) ?></td><td><?= e($a['patient_name']) ?> <span class="muted"><?= e($a['patient_code']) ?></span></td><td><?= e($a['doctor_name']) ?></td><td><?= e($a['reason'] ?? '') ?></td><td><?= status_badge($a['status']) ?></td>
<td><a href="<?= e(url('appointments/view.php?id=' . $a['appointment_id'])) ?>">Details</a><?= $a['visit_id'] ? ' · <a href="' . e(url('visits/view.php?id=' . $a['visit_id'])) . '">Visit</a>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php endfor; ?>
<?php page_end();
