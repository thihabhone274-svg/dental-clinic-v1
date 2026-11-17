<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();
if (is_post()) { require_post(); }
$pid = is_post() ? post_int('patient_id') : get_int('patient_id');
$aid = is_post() ? post_int('appointment_id') : get_int('appointment_id');
redirect('reception/checkin.php?' . http_build_query(['patient_id'=>$pid,'appointment_id'=>$aid]));
