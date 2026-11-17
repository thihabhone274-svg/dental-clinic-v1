<?php
// One-time setup: creates the FIRST administrator account.
// Run from a command prompt:   C:\xampp\php\php.exe scripts\create_first_admin.php
// It refuses to run if an active administrator already exists, and it cannot be run from a web browser.
require __DIR__ . '/_cli.php';
exit(cli_create_admin());
