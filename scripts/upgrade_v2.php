<?php
// CLI only; additive, repeat-safe upgrade of the demo or an isolated test database.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require __DIR__ . '/../includes/bootstrap.php';
try {
    $name=(string)q_val('SELECT DATABASE()');
    if ($name !== 'dental_clinic_v2_demo' && !str_ends_with($name,'_test')) { throw new RuntimeException('Upgrade targets only dental_clinic_v2_demo or an isolated *_test database.'); }
    if ((int)q_val('SELECT @@innodb_force_recovery') !== 0) { throw new RuntimeException('Database is in recovery mode. Use the clean instance first.'); }
    if (q_val("SELECT 1 FROM schema_version WHERE version='2.0-core'")) { echo "V2 Core is already installed. No changes needed.\n"; exit(0); }
    require __DIR__.'/backup_before_upgrade.php';
    echo 'Backup saved: '.backup_before_upgrade().PHP_EOL;
    $sql=file_get_contents(__DIR__.'/../database/migrations/002_core_workflow.sql');
    foreach (explode(';',$sql) as $statement) { if (trim($statement)!=='') { db()->exec($statement); } }
    foreach (['arrival_queue_entry','visit_note_revision','clinical_item'] as $table) { q("SELECT 1 FROM `$table` LIMIT 1"); }
    q('SELECT workflow_mode, clinical_status, administrative_status FROM clinical_visit LIMIT 1');
    q("INSERT INTO schema_version(version,description) VALUES ('2.0-core','Reception queue, clinical workspace and appointment rules')");
    echo "V2 CORE UPGRADE COMPLETE. Existing users and patients are retained.\n";
} catch (Throwable $e) { fwrite(STDERR,"Upgrade stopped: ".$e->getMessage()."\nNo tables were dropped. Fix the reported issue and rerun this script.\n"); exit(1); }
