# Assumptions, items needing clinic confirmation, and future requirements

This version was built from the Stage 1 Revision 2 specification and HTML prototype, the Stage 2.0 readiness report, and the implementation instructions. **Two source documents were never available to the author:** the revised Module 1-2 Software Design Specification and the original clinic registration/examination form image. Everything below that depends on them is **unverified**.

## 1. Fields and wording that still need clinic confirmation
| Area | What is implemented | What must be checked against the real clinic form |
|---|---|---|
| Patient | Name, registration number (system-generated `PAT-year-number`), date of birth, gender (free text), occupation, nationality, telephone, address, NRC (optional, never a key), registration date | Exact printed labels; whether NRC, age and registration date appear on the form; whether the paper "Reg. No." is the same as the generated number; required fields (only name and registration date are required now); gender/nationality wording and options |
| Medical history | Allergy status + allergy rows (name, severity, reaction), past medical history, regularly taken medication, free-text **Notes** (the blank box, per the confirmed decision) | Exact printed labels and options; purpose of the blank box |
| Dental history | 14 yes/no/unknown questions (extraction complications, flossing, bleeding gums, bad breath, sensitivity to sweet/hot/cold, tenderness when chewing, bruxism, orthodontic history, TMJ pain, TMJ clicking, tobacco, betel quid), last dental visit (text), brushing frequency (choice list), notes | Exact wording and answer format on the form; the **brushing frequency choices are provisional** (`config/clinic_options.php`) |
| Examination codes | Teeth condition G/F/P; oral hygiene G/F/P; gingival inflammation G/L; gingival recession G/L; occlusion 1/2/3; overbite (OB) + / N / -; overjet (OJ) + / N / -; lipline H/N/L; midline R/N/L; mouth opening (mm); malocclusion, TMJ and general notes | Whether each code set is exactly what is printed. **The meaning of the abbreviations is deliberately not interpreted or displayed.** The ASCII hyphen `-` is stored; confirm the printed symbol |
| Provisional examination additions | Chief complaint, presenting symptoms, duration, TMJ pain/clicking, extraoral and intraoral findings, tooth-specific findings, diagnosis/clinical impression, treatment plan, follow-up, additional notes | **Not confirmed clinic requirements.** They are labelled "Provisional" on screen, stored separately (`clinical_examination_extra`), and can be changed in `config/clinic_options.php` without touching the database |
| Timezone | `Asia/Yangon` | **Placeholder.** Set the clinic's real timezone in `config/app.php` |

## 2. Decisions taken and where they come from
**Confirmed by the project owner:** doctors read-only on medical and dental history; NRC optional and never a key; the blank medical-form box is a notes field; no claim of a legal digital signature; account requests and approval only for staff/doctors; no hard deletes; history preserved; examinations belong to visits and walk-ins are allowed; server-side authorization; prepared statements, validation, constraints, transactions and audit; the Administrator is an operational superuser; a STAFF+DOCTOR combination must not give doctors history-editing rights.

**Assumptions made to proceed (all changeable):**
| # | Assumption | How to change |
|---|---|---|
| 1 | One account cannot hold both STAFF and DOCTOR (ADMIN+DOCTOR and ADMIN+STAFF are allowed) | `validate_roles()` in `includes/auth.php` |
| 2 | Doctors see only patients in active care (appointment today or later, or an open visit) plus their own visits; other doctors' visits are not visible | `doctor_scope` in `config/app.php` |
| 3 | Walk-ins are opened by staff/admin (doctors only for patients in their active care, as themselves) | `visits/open.php` |
| 4 | Appointments need explicit start and end; no clinic-hours or slot rules; start times in the past are rejected; same-doctor overlaps are rejected; same-patient overlaps warn and need confirmation | `includes/appointments.php` |
| 5 | Reschedule/cancel/no-show are blocked once a visit exists for the appointment. A cancelled visit permanently uses its appointment link (the appointment must be cancelled and rebooked) | `includes/appointments.php`, `includes/visits.php` |
| 6 | An appointment becomes COMPLETED when its visit is closed; CONFIRMED and NO_SHOW are set manually (no-show only after the start time) | same |
| 7 | One current examination per visit. Drafts only while the visit is open. **A visit with a draft cannot be closed**; a draft can be discarded (kept, marked discarded) | `includes/exams.php`, `includes/visits.php` |
| 8 | A finalized examination is corrected (reason required) by adding a new finalized record; the original is marked superseded and never changed. Corrections are allowed after the visit is closed | `exam_correct()` |
| 9 | Examinations are entered by the visit's responsible doctor; an administrator may act for any doctor (recorded as the real actor and role) | `can_write_exam()` |
| 10 | Archived patients are read-only (no appointments, visits, histories or edits). Archiving is blocked while appointments are open or a visit is open. **There is no restore** | `patient_archive()` |
| 11 | Histories: an unchanged save creates no new version; "Not provided" is distinct from "No"; allergy status must agree with the allergy list | `includes/histories.php` |
| 12 | Duplicate review compares NRC, telephone and name+date of birth (strong) or name only (weak); it only warns | `patient_duplicates()` |
| 13 | Audit holds ids, field names, status changes and codes only; edits to patient details record which fields changed, not their values | `includes/audit.php` |
| 14 | Passwords 8-72 characters; sign-in throttled to 10 failures per 5 minutes per address; session idle timeout 120 minutes | `config/app.php` |
| 15 | Forgotten passwords: administrator sets a one-time temporary password and the user must change it at next sign-in. There is no email recovery | `account_reset_password()` |
| 16 | The administrator role is granted only by the console script or by an existing administrator re-entering their password | `role_grant()` |

## 3. Differences from the Stage 1 specification
- The Stage 1 "clinical note" workflow (separate note table with revisions) is **not built**. Chief complaint, diagnosis and plan are provisional fields inside the examination, which already has draft/finalize/correct history.
- An administrator can open patient records and perform staff and doctor work (Stage 1 had restricted administrator-only accounts). This follows the implementation instructions.
- No separate `staff` profile table; no migration-tracking table beyond `schema_version`.
- Added: administrator-assisted password reset, change password, sign-in throttle, `appointment_change` history table, `DISCARDED` draft status, finalizer columns, `must_change_password`.
- Not implemented from Stage 1: "mark history reviewed" (doctors are read-only; reviewed status is not tracked).

## 4. Not implemented (future requirements)
Restoring archived patients (needs a decision and a design); clinic opening hours, slot lengths, recurring appointments, doctor leave; reminders (SMS/email); patient merge; printing and PDF export of examinations or records; reports and statistics; tooth chart and image/X-ray attachments; treatment planning and billing/payments; inventory and payroll; multi-branch; patient portal; email-based password recovery; two-factor sign-in; audit-log export; a built-in backup screen; multiple languages; an in-app settings page (settings are edited in the `config` files).

## 5. Known limitations
- **Concurrency:** appointment and visit rules lock the relevant database row inside a transaction and recheck just before saving, which was tested with eight simultaneous bookings. This protects against simultaneous users of the application, but not against someone editing the database directly (for example through phpMyAdmin).
- **Direct database access** bypasses every rule here. Use the least-privilege database user and protect the MySQL `root` account.
- Immutability of history and audit rows at the database level exists only when the optional least-privilege user is used.
- The design was never viewed in a real browser by the author (see `docs/TESTING.md`); layout, colours and the confirmation dialogs need a visual check on the clinic's machine.
- The Windows-only password-hiding code in the console scripts has not been run on Windows.
