# Permissions and security

All role decisions are centralized in `includes/auth.php` (permission table `PERMISSIONS`, scope functions `can_access_patient`, `can_access_visit`, `can_write_exam`, `can_close_visit`). Every protected page calls these on the server. Hiding a button is never the only protection; the tests post directly to the URLs to prove it.

## Roles
| Action | Staff | Doctor | Administrator |
|---|:-:|:-:|:-:|
| Register / edit / archive patients | Yes | No | Yes |
| View patient profile | Yes | Only patients in active care* | Yes |
| **View** medical and dental history | Yes | Yes (read-only, same scope) | Yes |
| **Record** medical and dental history | Yes | **No** | Yes |
| Book, reschedule, cancel appointments; confirm; no-show | Yes | No | Yes |
| View appointments | All | Own only | All |
| Open a visit from an appointment | Yes | Own appointments | Yes |
| Open a walk-in/emergency visit | Yes | Patients in active care, themselves as doctor | Yes |
| Close or cancel a visit | Yes | Own visits | Yes |
| View visits and examinations | All | Own visits | All |
| Draft, finalize, discard, correct examinations | No | Own visits only | Yes (any visit; actor recorded) |
| Account requests, accounts, roles, password reset | No | No | Yes |
| Grant the Administrator role | No | No | Yes, re-entering own password |
| Audit log | No | No | Yes |
| Delete any patient or clinical record | **Nobody** | **Nobody** | **Nobody** |

\* **Active care** (setting `doctor_scope = strict`): the doctor has a SCHEDULED/CONFIRMED appointment with the patient today or later, or an OPEN visit with the patient. A doctor can always open **their own visits** (including closed ones) to review and correct their own examinations, but that does not grant the patient's profile or histories. Set `doctor_scope = all` in `config/app.php` to let doctors open every patient (less private).

## Role combinations
One account **cannot hold both STAFF and DOCTOR** (enforced when approving requests and when granting roles; `scripts/check_setup.php` reports violations). This stops a doctor gaining history-editing rights through the staff role. An administrator already has both abilities. An Administrator account that is also a clinic doctor can additionally hold DOCTOR (so they appear in doctor lists).

## Administrator accounts
The public request form offers only Staff and Doctor, and the server rejects anything else (and audits the attempt). The approval screen cannot assign Administrator. Administrator is granted only (a) by the one-time console script `create_first_admin.php`, or (b) on **Accounts and roles** by an existing administrator who re-enters their password. The last administrator cannot be deactivated or demoted, and nobody can deactivate or demote themselves.

## Audit
Every create/update/finalize/correct/approval/denial is written to `audit_event` in the **same transaction** as the change (if the audit insert fails, the change is rolled back). Entries contain the real signed-in user, their role, the action code, the record id, outcome, request id and IP, plus a short summary limited to ids, **field names**, status changes, version numbers and codes. Free text, clinical notes, names, phone numbers and passwords are never written to the audit log (values that do not fit a strict pattern are replaced by "(redacted)").

## Other protections
CSRF token on every POST; prepared statements everywhere; output escaping; session id regenerated at sign-in; idle timeout; sign-in throttle (10 failed attempts per 5 minutes per address); generic sign-in error; `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`; passwords 8-72 characters hashed with `password_hash`; errors never show SQL, paths or credentials. Optional least-privilege database user (`database/optional_create_app_user.sql`) gives INSERT/SELECT only on history, audit and change tables and no DELETE anywhere, so even a coding mistake cannot edit those records.
