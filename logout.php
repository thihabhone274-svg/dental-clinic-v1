<?php
require __DIR__ . '/includes/bootstrap.php';
if (!current_user()) { redirect('login.php'); }
require_post();
logout_now();
flash('success', 'You have been signed out.');
redirect('login.php');
