# Database

Single setup file: `database/dental_clinic_setup.sql` (MariaDB 10.4 syntax, InnoDB, utf8mb4, `utf8mb4_unicode_ci`). It creates the database `dental_clinic` if missing and 16 tables. Reference data: the three roles only.

| Table | Purpose |
|---|---|
| `role`, `user_account`, `user_role` | Accounts and role grants (grants are kept as history: `active_flag` is NULL once revoked) |
| `account_request` | Public staff/doctor requests (role limited to STAFF/DOCTOR by a CHECK) |
| `doctor` | Doctor profile for a user (needed for appointments and visits) |
| `patient` | Demographics; generated registration number; `archived_at` instead of deletion |
| `medical_history_version`, `allergy_item`, `dental_history_version` | Versioned histories (new row per save) |
| `appointment`, `appointment_change` | Bookings and the append-only history of reschedules and cancellations |
| `clinical_visit` | Visits; `appointment_id` is optional (walk-ins) and unique (one visit per appointment) |
| `clinical_examination`, `clinical_examination_extra` | Examinations (documented columns) and provisional fields (key/value, configured in `config/clinic_options.php`) |
| `audit_event` | Append-only audit trail |
| `schema_version` | Records the schema version |

**Rules the database enforces:** unique codes and usernames; one active grant per user and role; end after start for appointments; non-negative mouth opening; a correction must have a reason; one correction per examination; a visit's appointment link matches its type; birth date not after registration; role restricted on account requests; all foreign keys `RESTRICT` (nothing cascades or deletes).
**Rules only the application enforces:** doctor/patient overlap, the visit-close rules, role combinations, scope, immutability of finalized records and history (add the least-privilege user to make history/audit immutable at the database level).

## MariaDB 10.4 compatibility
The SQL avoids features newer than 10.4 (no `RETURNING`, `SKIP LOCKED`, `UUID`/`INET6` types, JSON type, generated columns). **It was tested only on MariaDB 10.11.14**, not on 10.4.32. After importing on your machine run `scripts/check_setup.php`, then the tests in `docs/TESTING.md`; they verify that CHECK constraints and foreign keys really work on your server.

## Time
PHP and the database session both use the timezone in `config/app.php`. `DATETIME` columns hold clinic-local time.
