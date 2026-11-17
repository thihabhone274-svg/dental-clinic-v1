<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_can('appointment.view');
$id = get_int('id'); $a = appt_find($id);
if (!$a) { abort(404, 'Not found', 'That appointment does not exist.'); }
if (!has_role('ADMIN') && !has_role('STAFF') && (int) $a['doctor_id'] !== (int) $u['doctor_id']) { deny('You can view only your own appointments.'); }
$open = in_array($a['status'], ['SCHEDULED', 'CONFIRMED'], true); $blocked = appt_has_blocking_visit($id);
$canVisit = $open && !$a['visit_id'] && can('appointment.write') && $a['patient_archived'] === null;
page_start('Appointment ' . $a['appointment_no'], ['nav' => 'appointments', 'crumbs' => [['Appointments', 'appointments/index.php']]]);
?>
<div class="grid-main-side"><section class="panel"><div class="row-between"><h2><?= status_badge($a['status']) ?></h2></div><dl class="dl">
<?= dl_row('Patient', $a['patient_name'] . ' (' . $a['patient_code'] . ')') ?><?= dl_row('Doctor', $a['doctor_name']) ?><?= dl_row('Date', date('l, j F Y', strtotime($a['start_at']))) ?>
<?= dl_row('Time', date('H:i', strtotime($a['start_at']))) ?><?= dl_row('Reason', $a['reason']) ?><?= dl_row('Note', $a['note'], true) ?>
<?php if ($a['status'] === 'CANCELLED'): ?><?= dl_row('Cancelled', fmt_dt($a['cancelled_at'])) ?><?= dl_row('Cancellation reason', $a['cancellation_reason']) ?><?php endif; ?>
<?php if ($a['visit_id']): ?><div class="dl-row"><dt>Visit</dt><dd><a href="<?= e(url('visits/view.php?id=' . $a['visit_id'])) ?>">Open visit</a> <?= status_badge($a['visit_status']) ?></dd></div><?php endif; ?></dl>
<div class="form-actions">
<?php if ($canVisit): ?><a class="btn btn-primary" href="<?= e(url('reception/checkin.php?appointment_id='.$id)) ?>">Check in patient</a><?php endif; ?>
<?php if (can('appointment.write') && $open && !$blocked): ?>
  <a class="btn btn-secondary" href="<?= e(url('appointments/form.php?id=' . $id)) ?>">Reschedule</a>
  <a class="btn btn-secondary" href="<?= e(url('appointments/reassign.php?id=' . $id)) ?>">Reassign doctor</a>
  <?= $a['status'] === 'SCHEDULED' ? post_button('appointments/status.php', ['id' => $id, 'to' => 'CONFIRMED'], 'Mark confirmed', 'btn-secondary', 'Mark this appointment as confirmed?') : '' ?>
  <?= post_button('appointments/status.php', ['id' => $id, 'to' => 'NO_SHOW'], 'Mark no-show', 'btn-secondary', 'Mark the patient as a no-show?') ?>
  <details class="drop"><summary class="btn btn-danger btn-sm">Cancel appointment…</summary><form method="post" action="<?= e(url('appointments/cancel.php')) ?>" class="drop-body" data-confirm="Cancel this appointment? The record is kept.">
   <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?= f_textarea('reason', 'Reason for cancelling', [], [], ['required' => true, 'rows' => 2]) ?><button class="btn btn-danger btn-sm">Cancel appointment</button></form></details>
<?php endif; ?></div></section>
<aside class="panel"><h3>History</h3><?php $ch = appt_changes($id); if (!$ch): ?><p class="muted">No reschedules or cancellations.</p><?php else: ?><ul class="plain"><?php foreach ($ch as $c): ?>
<li><strong><?= e(label($c['change_type'])) ?></strong> <span class="muted"><?= e(fmt_dt($c['changed_at'])) ?> by <?= e($c['changed_by_name']) ?></span><?php if ($c['change_type']==='REASSIGNED'): ?><br>Doctor #<?= (int)$c['old_doctor_id'] ?> → #<?= (int)$c['new_doctor_id'] ?><?php endif; ?><br>Was <?= e(fmt_dt($c['old_start_at'])) ?><?= $c['new_start_at'] ? '; now ' . e(fmt_dt($c['new_start_at'])) : '' ?><?= $c['reason'] ? '<br><span class="muted">' . e($c['reason']) . '</span>' : '' ?></li><?php endforeach; ?></ul><?php endif; ?></aside></div>
<?php page_end();
