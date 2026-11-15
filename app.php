<?php
// Application settings (not secret). Database credentials live in config/database.php.
return [
    'app_name'    => 'Dental Clinic Management System',
    // CLINIC TIMEZONE: the clinic's timezone was never confirmed. This is a PLACEHOLDER; change it to your
    // clinic's zone (list: https://www.php.net/manual/en/timezones.php). All times are stored and shown in this zone.
    'timezone'    => 'Asia/Yangon',
    // 'strict' = a doctor may open only patients in active care (an appointment today or later, or an open visit)
    //            plus their own visits. 'all' = a doctor may open every patient record (simpler, less private).
    'doctor_scope' => 'strict',
    'base_path'   => null,          // null = auto-detect (e.g. /dental_clinic under XAMPP htdocs)
    'session_name' => 'DENTALSESSID',
    'session_idle_minutes' => 120,
    'password_min_length' => 8,
    'page_size'   => 20,
    'login_max_failures_per_5min' => 10,
];
