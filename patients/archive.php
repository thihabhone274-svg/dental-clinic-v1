<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login();
require_post();
require_can('patient.archive');
$id = post_int('id'); $p = patient_find($id);
if (!$p) { abort(404, 'Not found', 'That patient record does not exist.'); }
$e = []; $reason = v_text($_POST, 'reason', $e, 'Reason', ['required' => true, 'max' => 500]);
if (!$e && trim((string) ($_POST['confirm_code'] ?? '')) !== $p['patient_code']) { $e['confirm_code'] = 'The registration number you typed does not match.'; }
if ($e) { flash('error', implode(' ', $e)); redirect('patients/view.php?id=' . $id); }
try { patient_archive($id, $reason, (int) $u['user_id']); flash('success', 'Patient archived. The record is now read-only.'); }
catch (DomainException $ex) { flash('error', $ex->getMessage()); }
redirect('patients/view.php?id=' . $id);
