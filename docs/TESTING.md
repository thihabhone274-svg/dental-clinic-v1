# Testing

## What was tested, and where
All automated tests below were run by the author in a **Linux sandbox with PHP 8.3.6 and MariaDB 10.11.14**, not on Windows, not on XAMPP, not on PHP 8.2.12 and not on MariaDB 10.4.32. Treat the results as strong evidence that the code and SQL are consistent and the rules work, **not** as proof that it runs on your computer. Run the same tests on your machine (below) to close that gap.

| Check | How | Result observed |
|---|---|---|
| PHP syntax of every file | `tests/lint_all.php` | 58 PHP files, 0 syntax errors |
| JavaScript syntax | `node --check assets/js/app.js` | parses |
| Setup SQL imports on a clean server | imported into an empty database | 16 tables, 38 foreign keys, 6 CHECK constraints, 3 roles; no errors |
| SQL used by the application matches the schema | `tests/check_sql_consistency.php` prepares every static SQL statement against the real schema | 120 statements, 0 mismatches (12 dynamic fragments are covered by the integration tests) |
| Setup checker | `scripts/check_setup.php` | correct OK/WARN/FAIL output, including a missing database |
| Application behaviour (HTTP, all roles) | `tests/run_tests.php` drives the real application as anonymous, staff, doctor, doctor 2 and administrator | **196 checks passed, 0 failed**; repeated three times in a row and also against a multi-worker server; **no PHP error or warning was logged** |
| Same suite as the least-privilege database user | the user from `database/optional_create_app_user.sql` (no UPDATE/DELETE on history/audit tables, no DELETE anywhere) | 196 passed, 0 failed |
| Concurrent bookings | `tests/run_concurrency_test.php`: 8 simultaneous requests for the same doctor and time | exactly 1 booking saved |
| Database failure handling | wrong credentials, then a login POST | friendly error page with a reference number; no SQL, credentials, file paths or stack trace shown; detail only in the server log |
| Rendered pages (author's check, not shipped) | fetched 24 screens as the administrator, including the examination form in its normal and validation-error states, and parsed the HTML | balanced markup, no inline scripts or event handlers (so the Content-Security-Policy blocks nothing), every page has its heading |
| Console scripts | first administrator (second run refused), recovery (typed confirmation required; password changed and audited), demo data, test-database creator (refuses an existing name or a name not ending `_test`) | behaved as designed |

### What the 196 application checks cover
Sign-in (wrong password, unknown user, generic message), sign-out, session protection, anonymous redirect for 7 URLs; denied access by direct URL for staff and doctors (admin pages, patient registration, appointment booking, examination form) and the denial being audited; CSRF rejection with missing or wrong token; patient validation messages next to the fields, creation, search by name/telephone/registration number, empty state, HTML escaping of hostile input, duplicate warning without saving and registration after confirmation, edit with field-name-only audit; medical history validation, versioning, "no change creates no version", previous versions preserved, dental history structured answers ("Not provided" never becomes "No"); **doctor cannot save histories (403, nothing changed)** and sees no edit control; a doctor without active care cannot open a patient; administrator can record histories and patients; appointment validation, doctor conflict rejected, patient overlap warning and confirmation, reschedule history, cancel with reason, doctor sees only own bookings; visits from an appointment (other-date confirmation, no duplicate visit) and walk-ins; staff cannot enter examinations; examination validation, draft save/edit, visit cannot close with a draft, finalize, finalized record cannot be edited, visit closes and the appointment completes, **correction preserves the original record unchanged**, no double correction, administrator performs doctor functions with the real actor recorded, discard, visit cancel rules; archive rules (typed code, blocked by open work, read-only afterwards); account request (ADMIN refused), approval (cannot assign ADMIN), STAFF+DOCTOR refused, ADMIN grant needs password, revoke keeps history, cannot deactivate self, temporary-password reset forces a change, deactivated users are signed out; audit log access and the check that **no clinical text appears in any audit entry**; every dashboard link works for each role; missing records return 404.

## What was NOT tested
- **Windows, XAMPP, PHP 8.2.12, MariaDB 10.4.32** (the real target). The SQL and PHP avoid newer-only features and a scan found no PHP 8.3-only functions, but this is not a substitute for running it.
- **Any web browser.** No page was viewed in a browser. Layout, colours, spacing, responsive behaviour, and the JavaScript confirmation dialogs (`<dialog>`) and "add allergy row" button are unverified. The server-side behaviour behind them is tested.
- Apache `.htaccess` blocking of `config/`, `includes/` etc. (the test server does not read `.htaccess`). Check by opening `http://localhost/dental_clinic/config/app.php` and `.../includes/db.php`: both must show "Forbidden".
- The Windows password-hiding code in the console scripts (uses PowerShell).
- Accessibility, performance with large data, printing, and very long free-text input beyond the validated limits.
- Any browser other than what you use.

## Running the tests on your computer (Windows)
The tests use a **separate, throw-away database whose name ends in `_test`** and refuse to run against anything else.
```
cd C:\xampp\htdocs\dental_clinic
copy tests\config.test.example.php tests\config.test.php
```
Edit `tests\config.test.php` only if your MySQL `root` account has a password (the test configuration is the only place root is used).
```
set DENTAL_CONFIG=C:\xampp\htdocs\dental_clinic\tests\config.test.php
C:\xampp\php\php.exe scripts\create_test_database.php
C:\xampp\php\php.exe tests\lint_all.php
C:\xampp\php\php.exe tests\check_sql_consistency.php
start "test server" C:\xampp\php\php.exe -S 127.0.0.1:8099 -t C:\xampp\htdocs\dental_clinic
C:\xampp\php\php.exe tests\run_tests.php http://127.0.0.1:8099
```
(Run all of them in the same Command Prompt window so `DENTAL_CONFIG` is shared; close the "test server" window afterwards.) The test run needs PHP's `curl` extension (enabled by default in XAMPP). Expected end of output: `RESULT: 196 passed, 0 failed`. If sign-in checks fail, wait 5 minutes: the application's sign-in throttle may be active.
`tests\run_concurrency_test.php` needs a server that handles requests in parallel; PHP's built-in server on Windows handles one at a time, so run it only on Linux/macOS (`PHP_CLI_SERVER_WORKERS=6 php -S ...`) or against Apache pointed at the test database.
To start over, drop the `dental_clinic_test` database in phpMyAdmin and run `create_test_database.php` again.

## Manual acceptance checklist (needs a browser)
1. Sign in as administrator; open every sidebar item; confirm the layout looks correct on a laptop-size window and on a narrow window (the menu button appears).
2. Request a Doctor account from the sign-in page; approve it; sign in as that doctor; confirm the doctor menu shows only Dashboard, My patients, Appointments, Visits.
3. Request a Staff account; approve; sign in; register a patient; try registering the same name and birth date again and confirm the duplicate panel.
4. Record a medical history with an allergy (use **Add another allergy**); save a second version; open version 1 and confirm it is unchanged.
5. As the doctor, open the patient (after booking an appointment for that doctor) and confirm the history pages have no edit buttons.
6. Book an appointment; try booking the same doctor at an overlapping time and confirm it is refused; reschedule it; cancel one (a confirmation dialog should appear first).
7. Open the visit from the appointment; as the doctor start an examination, save a draft, try to close the visit (blocked), finalize (confirmation dialog), close the visit.
8. Correct the finalized examination and confirm both the new and the superseded record can be viewed.
9. Open a walk-in visit; discard a draft; cancel the visit.
10. Reset a user's password as administrator; sign in with the temporary password and change it.
11. Open the audit log; confirm it contains the actions above and no clinical text.
12. Open `http://localhost/dental_clinic/config/app.php` and confirm "Forbidden".
