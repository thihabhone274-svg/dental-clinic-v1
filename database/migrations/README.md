# Database migrations

Import `../dental_clinic_setup.sql` only for a fresh installation. Apply `002_core_workflow.sql` through `scripts/upgrade_v2.php` from the project root; the script creates a backup and records `2.0-core` in `schema_version`. An already-completed upgrade is skipped. A partially failed upgrade requires diagnosis before retrying.
