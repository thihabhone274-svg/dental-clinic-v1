# V2 Core validation — 16 November 2026

Environment: isolated PHP 8.4.27, MariaDB 10.11.18 and Chromium headless. No access to the user's Windows installation or live database was used.

- 45 database-backed service checks passed: registration, duplicate queue guards, role/ownership restrictions, Quick and Standard completion, draft handling, note revision retention, treatment type separation, tooth/surface validation, void history, upgrade preserving notes, appointment spacing boundaries, explicit patient override, separate doctor reassignment/history, checked-in appointment guards and final closure.
- 443 patient-input checks passed, covering Myanmar NRC handling and registration inputs.
- PHP syntax checks passed across the package.
- Browser: administrator, reception and doctor login; dashboard, queue and visits pages; administrator accounts, settings, appointments and patients; creation of a doctor account through the actual form.
- Browser: doctor claims a waiting patient, saves a note, uses the tooth selector, saves a finding, confirms clinical completion, and verifies the saved note becomes read-only. Desktop and 390-pixel mobile screenshots inspected; no horizontal overflow on dashboard/clinical workspace.
- Additive migration completed and a second run correctly skipped it.
- SQL backup restored into a separate empty test database; row counts matched for all 20 tables.

The older tests/run_tests.php covers the V1 behavior and is not the V2 acceptance suite. tests/core_workflow_test.php requires a fresh isolated *_test database with baseline schema and V2 migration already applied; it creates fictional test records and must not be run against the demo database.

Remaining manual checks on the user's machine: upgrade with the actual PHP 8.2/MariaDB 10.4 XAMPP installation, real Myanmar keyboard/font behavior, teacher review of terminology and workflow, and final clinical/business rules. Successful automated checks do not replace these acceptance checks.
