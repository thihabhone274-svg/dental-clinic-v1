# Dental Clinic Management System — V2 Core

A local PHP and MariaDB prototype for a dental clinic, developed as a third-year Computer Science project with AI assistance. This milestone connects patient registration, appointments, reception check-in, clinical work, and visit closure.

**Status:** functional prototype for lecturer and clinic review. The workflow and terminology are still being refined. Intended for local XAMPP evaluation with fictional patient data.

## What you can explore

| Area | V2 Core functionality |
| --- | --- |
| Patients | Registration, search, profiles, medical/dental history, Myanmar NRC inputs |
| Appointments | Booking, rescheduling, separate doctor reassignment, spacing checks |
| Reception | Check-in, waiting queue, reception review, visit closure |
| Clinical workspace | Quick notes or Standard examinations; findings, recommendations, performed treatments; FDI tooth selection |
| Record history | Note revisions, voided clinical entries, examination corrections |
| Administration | Staff/doctor account creation, account requests, settings, audit log |

## Demonstration workflow

1. Create Staff and Doctor accounts as administrator.
2. Register a fictional patient as Staff and book an appointment or check in a walk-in.
3. The responsible Doctor claims the queue entry and starts a Quick or Standard visit.
4. Save a note for Quick, or finalize an examination for Standard. Add clinical entries as needed.
5. The Doctor completes clinical work.
6. Staff completes reception review and closes the visit.

Clinical completion and reception review are separate. Closing a visit does not record payment. Administrators without the responsible doctor role cannot author clinical records.

See [the detailed V2 guide](START_V2.md) for the walkthrough and rules.

## Technology and learning context

PHP, MariaDB/MySQL, HTML, CSS, and JavaScript; server-rendered pages without a framework, Composer, or a Node build step. AI tools supported development. The project owner works on requirements, workflows, UI/UX review, and iterative testing while continuing to learn backend implementation. This repository demonstrates an evolving prototype, not independently verified production readiness.

## Run locally: fresh installation

Target environment: XAMPP on Windows, PHP 8.2 with PDO MySQL and mbstring, and MariaDB 10.4. The supplied historical validation report used PHP 8.4 and MariaDB 10.11; exact XAMPP compatibility still needs local verification.

1. Download this branch and place its contents in `C:\xampp_repair\htdocs\dental_clinic_v2_demo`. Keep all subfolders intact. If your XAMPP folder differs, adjust the paths below.
2. Start Apache and your intended MariaDB instance. Do not start a second instance on the same port.
3. For a **new, empty installation only**, import `database/dental_clinic_setup.sql` through phpMyAdmin. It creates `dental_clinic_v2_demo` and the baseline schema.
4. Review `database/optional_create_app_user.sql`, replace its password placeholder, and run it to create the local application database account.
5. Copy `config/database.example.php` to `config/database.php`. Set the actual host, port, username, and password. Keep this file private.
6. Check `config/app.php` for clinic settings and timezone.
7. From the project folder run:

```bat
C:\xampp_repair\php\php.exe scripts\create_first_admin.php
C:\xampp_repair\php\php.exe scripts\upgrade_v2.php
C:\xampp_repair\php\php.exe scripts\check_setup.php
```

The upgrade makes a pre-upgrade backup outside `htdocs` and applies the V2 migration. It accepts `dental_clinic_v2_demo` or an isolated database ending in `_test`.

8. Open `http://localhost/dental_clinic_v2_demo/` and sign in.

**Already have the working demo?** Preserve your local `config/database.php` and existing database. Follow [START_V2.md](START_V2.md); do not re-import the baseline or recreate accounts.

## Repository map

| Folder | Purpose |
| --- | --- |
| `patients/`, `histories/` | Patient records and intake histories |
| `appointments/`, `reception/`, `visits/` | Scheduling, arrivals, and visit lifecycle |
| `examinations/` | Examination screens |
| `admin/`, `auth/` | Administration and account screens |
| `includes/` | Shared application and business logic |
| `assets/` | CSS, JavaScript, and NRC reference data |
| `config/` | Application settings and example database configuration |
| `database/` | Baseline schema and numbered migrations |
| `scripts/` | Setup, backup, and upgrade commands |
| `tests/` | Input validation and database workflow tests |
| `docs/` | Design notes, historical validation, and review findings |
| `storage/` | Local runtime logs, excluded from Git |

## Verification

The uploaded [V2 Core test report](docs/V2_CORE_TEST_REPORT.md) records 45 database service checks and 443 input checks from an earlier isolated environment. Those are historical results, not tests rerun during this GitHub preparation.

For a database-free input check:

```bat
C:\xampp_repair\php\php.exe tests\patient_input_test.php
```

`tests/core_workflow_test.php` needs a fresh isolated `_test` database with both baseline and V2 migration applied. It creates test records; do not run it against your working demo. Older V1 tests are not the V2 acceptance suite.

## Known limits and next steps

- Billing, payments, packages, and inventory are outside this milestone.
- Intake forms versus clinical examinations and everyday clinic UX still need lecturer/clinic review.
- Clinical notes and treatment entries freeze after completion; late amendments for those entries are not implemented.
- Tooth selection is not a full graphical odontogram. Queue refresh is manual.
- The upgrade creates a one-time backup; scheduled backups are not implemented.
- Exact Windows/XAMPP operation and real keyboard/font behaviour require local testing.
- Older documents can describe V1 rules. Prefer this README and START_V2 for current scope.

## Troubleshooting

Check `scripts/check_setup.php` for configuration problems and `storage/logs/php_errors.log` for error references. Confirm the running database port matches local configuration. Refresh the browser with Ctrl+F5 after replacing assets. Keep database backups outside the repository and use fictional data in shared demonstrations.
