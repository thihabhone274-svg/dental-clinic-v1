<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_login(); require_post(); require_can('appointment.write');
$id = post_int('id'); $to = (string) ($_POST['to'] ?? '');
if (!in_array($to, ['CONFIRMED', 'NO_SHOW'], true)) { abort(400, 'Invalid request', 'Unknown status.'); }
try { appt_set_status($id, $to, (int) $u['user_id']); flash('success', 'Appointment marked ' . strtolower(label($to)) . '.'); } catch (DomainException $ex) { flash('error', $ex->getMessage()); }
redirect('appointments/view.php?id=' . $id);
