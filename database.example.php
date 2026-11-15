<?php
// Copy this file to config/database.php and edit. config/database.php is never served by Apache (see config/.htaccess).
// Do NOT use root for normal operation: create the dedicated user with database/optional_create_app_user.sql.
return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'name'     => 'dental_clinic',
    'user'     => 'dental_app',
    'password' => 'CHANGE_THIS_PASSWORD',
];
