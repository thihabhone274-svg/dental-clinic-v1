<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login(); require_post(); require_can('appointment.write');
$id = post_int('id'); $e = [];
$reason = v_text($_POST, 'reason', $e, 'Reason', ['required' => true, 'max' => 500]);
if ($e) { flash('error', $e['reason']); redirect('appointments/view.php?id=' . $id); }
try { appt_cancel($id, $reason, (int) $u['user_id']); flash('success', 'Appointment cancelled. The record and its history are kept.'); } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
redirect('appointments/view.php?id=' . $id);
