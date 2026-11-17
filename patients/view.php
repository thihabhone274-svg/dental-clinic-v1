<?php
require __DIR__ . '/../includes/bootstrap.php';
$id = get_int('id');
$p = require_patient_access($id);
$tab = (string) ($_GET['tab'] ?? 'overview'); if (!in_array($tab, ['overview', 'appointments', 'visits'], true)) { $tab = 'overview'; }
$archived = $p['archived_at'] !== null;
$actions = '';
if (can('patient.write') && !$archived) { $actions .= '<a class="btn btn-secondary" href="' . e(url('patients/edit.php?id=' . $id)) . '">Edit details</a> '; }
if (can('appointment.write') && !$archived) { $actions .= '<a class="btn btn-secondary" href="' . e(url('appointments/form.php?patient_id=' . $id)) . '">Book appointment</a> '; }
if (can('appointment.write') && !$archived) { $actions .= '<a class="btn btn-primary" href="' . e(url('visits/open.php?patient_id=' . $id)) . '">Check in patient</a>'; }
page_start($p['full_name'], ['nav' => 'patients', 'crumbs' => [['Patients', 'patients/index.php']], 'actions' => $actions]);
patient_header($p, $tab);
if ($tab === 'overview'):
  $med = med_latest($id); $den = dental_latest($id);
?>
<div class="grid-2">
<section class="panel"><h3>Demographics</h3><dl class="dl">
<?= dl_row('Registration number', $p['patient_code']) ?><?= dl_row('Date of birth', $p['date_of_birth'] ? $p['date_of_birth'] . ' (age ' . age_from($p['date_of_birth']) . ')' : null) ?>
<?= dl_row('Gender', $p['gender']) ?><?= dl_row('Occupation', $p['occupation']) ?><?= dl_row('Nationality', $p['nationality']) ?><?= dl_row('Telephone', $p['phone']) ?>
<?= dl_row('NRC', $p['nrc_number']) ?><?= dl_row('Address', $p['address'], true) ?><?= dl_row('Registered', $p['registration_date']) ?></dl></section>
<div class="stack">
<section class="panel"><h3>Medical history</h3>
 <?php if (!$med): ?><p class="muted">No medical history recorded yet.</p><?php else: ?>
 <p><?= status_badge($med['allergy_status']) ?> <span class="muted">version <?= (int) $med['version_no'] ?>, <?= e(fmt_date($med['recorded_at'])) ?></span></p>
 <?php if ($med['allergies']): ?><p class="alert alert-warn"><strong>Allergies:</strong> <?= e(implode(', ', array_map(fn($a) => $a['allergen_name'] . ($a['severity'] !== 'UNKNOWN' ? ' (' . strtolower($a['severity']) . ')' : ''), $med['allergies']))) ?></p><?php endif; endif; ?>
 <a href="<?= e(url('histories/medical.php?patient_id=' . $id)) ?>">Open medical history</a></section>
<section class="panel"><h3>Dental history</h3>
 <?php if (!$den): ?><p class="muted">No dental history recorded yet.</p><?php else: ?><p class="muted">Version <?= (int) $den['version_no'] ?>, recorded <?= e(fmt_date($den['recorded_at'])) ?> by <?= e($den['recorded_by_name']) ?></p><?php endif; ?>
 <a href="<?= e(url('histories/dental.php?patient_id=' . $id)) ?>">Open dental history</a></section>
<?php if (can('patient.archive') && !$archived): ?>
<section class="panel danger-zone"><h3>Archive patient</h3><p class="muted">Archiving makes the record read-only. Records are never deleted. Restoring an archived patient is not available in this version.</p>
<details class="drop"><summary class="btn btn-danger btn-sm">Archive this patient…</summary>
<form method="post" action="<?= e(url('patients/archive.php')) ?>" class="drop-body" data-confirm="Archive this patient? The record becomes read-only and cannot be restored in this version.">
 <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
 <?= f_textarea('reason', 'Reason (administrative, no clinical details)', [], [], ['required' => true, 'rows' => 2]) ?>
 <?= f_text('confirm_code', 'Type the registration number to confirm: ' . $p['patient_code'], [], [], ['required' => true]) ?><button class="btn btn-danger btn-sm">Archive patient</button></form></details></section>
<?php elseif ($archived): ?><section class="panel"><h3>Archived</h3><p class="muted">Archived <?= e(fmt_dt($p['archived_at'])) ?>. Reason: <?= e($p['archive_reason'] ?? '') ?></p></section><?php endif; ?>
</div></div>
<?php elseif ($tab === 'appointments'): $appts = appt_list(['patient_id' => $id]); ?>
  <?php if (!$appts): ?><div class="empty-state"><h2>No appointments</h2><p>This patient has no appointments yet.</p></div><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>No.</th><th>Date and time</th><th>Doctor</th><th>Reason</th><th>Status</th></tr></thead><tbody>
  <?php foreach (array_reverse($appts) as $a): ?><tr><td><a href="<?= e(url('appointments/view.php?id=' . $a['appointment_id'])) ?>"><?= e($a['appointment_no']) ?></a></td><td><?= e(fmt_dt($a['start_at'])) ?></td><td><?= e($a['doctor_name']) ?></td><td><?= e($a['reason'] ?? '') ?></td><td><?= status_badge($a['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php else: [$visits] = visit_list(['patient_id' => $id], 100, 0); $exams = exams_for_patient($id, (!has_role('ADMIN') && !has_role('STAFF') && cfg('doctor_scope') !== 'all') ? (int) current_user()['doctor_id'] : null); ?>
  <h3>Visits</h3>
  <?php if (!$visits): ?><div class="empty-state small"><p>No visits yet.</p></div><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Visit</th><th>Date</th><th>Doctor</th><th>Type</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($visits as $r): ?><tr><td><?= can_access_visit($r) ? '<a href="' . e(url('visits/view.php?id=' . $r['visit_id'])) . '">' . e($r['visit_no']) . '</a>' : e($r['visit_no']) ?></td><td><?= e(fmt_dt($r['visited_at'])) ?></td><td><?= e($r['doctor_name']) ?></td><td><?= e(label($r['visit_type'])) ?></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  <h3>Examinations</h3>
  <?php if (!$exams): ?><div class="empty-state small"><p>No examinations recorded.</p></div><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Examined</th><th>Visit</th><th>Doctor</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($exams as $x): ?><tr><td><?= e(fmt_dt($x['examined_at'])) ?></td><td><?= e($x['visit_no']) ?></td><td><?= e($x['doctor_name']) ?></td><td><?= status_badge($x['status']) ?></td><td><a href="<?= e(url('examinations/view.php?id=' . $x['exam_id'])) ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php endif; ?>
<?php page_end();
