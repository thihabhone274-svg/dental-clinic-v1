# Upgrade your working demo to V2 Core

This is the core reception-to-clinical-visit milestone. It includes real database-backed workflows, not a visual mock-up. Billing, payments, packages and inventory are not part of this milestone.

## Your existing XAMPP installation

1. Keep Apache and your working clean MariaDB server running. If you start MariaDB in a command window using data_v2_clean/my.ini, keep that window open. Do not start a second MySQL instance from XAMPP.
2. Close the clinic's browser tabs while upgrading. Extract this ZIP. Copy everything INSIDE its dental_clinic_v2_demo folder into C:\xampp_repair\htdocs\dental_clinic_v2_demo. Choose Replace for existing application files. Do not delete the destination folder first.
3. Your config/database.php is deliberately absent from the ZIP, so your working database settings stay in place. Keep your existing file.
4. Double-click UPGRADE_V2.cmd in C:\xampp_repair\htdocs\dental_clinic_v2_demo. Wait for V2 CORE UPGRADE COMPLETE. It backs up the database first to C:\xampp_repair\clinic_backups and then adds the new tables/columns. Rerunning is safe after success.
5. Open http://localhost/dental_clinic_v2_demo/ and sign in with your existing administrator. Press Ctrl+F5 once to refresh the styles.

Do not create another database, re-import the baseline SQL, copy MySQL data folders, or recreate your administrator for this upgrade. If the launcher reports an error, copy the message; do not continue by deleting database files.

Alternative command, run inside the application directory:
```
C:\xampp_repair\php\php.exe scripts\upgrade_v2.php
```

## Try the complete workflow

1. As administrator, go to Accounts and roles > Create staff or doctor account. Create one Staff login and one Doctor login. Choose passwords of at least eight characters. The Doctor account automatically gets a doctor profile.
2. As administrator or Staff, register a fictional patient. NRC can use region 1–14, a dependent township selector, citizenship type and six digits. Alternate/unknown identification remains supported.
3. Book an appointment, or choose Check in patient for a walk-in. The patient enters the waiting queue; this does not yet start a clinical visit.
4. Sign out and sign in as the assigned Doctor. Open Reception & waiting queue, choose Quick or Standard, then Start my visit.
5. Quick: save a clinical note. Standard: save and finalize an examination. Quick can be upgraded to Standard while preserving records.
6. Add findings, recommendations or performed treatments. Select teeth using the FDI buttons; optional surfaces use the displayed codes. These are separate clinical records, not invoices.
7. Doctor chooses Complete clinical work and confirms. Saved notes and treatment entries become read-only.
8. As Staff or administrator, open the visit, tick Patient details and follow-up reviewed, and complete reception review. Close completed visit becomes available when both parts are complete. A linked appointment becomes Completed.

The same browser has one signed-in user at a time. Sign out between roles, or use a separate private browser window.

## Main rules implemented

- One waiting entry per patient; an open visit blocks another check-in.
- Assigned patients can be claimed only by their responsible doctor; unassigned walk-ins may be claimed by an active doctor.
- Staff and administrators without the responsible doctor role cannot author clinical notes, examinations or treatment records.
- Quick requires a saved note; Standard requires a finalized examination. A draft examination blocks completion in either mode.
- Clinical completion and reception review are separate. Neither means payment was received.
- Doctor appointment spacing defaults to 30 minutes and cannot be overridden. Patient spacing conflicts require explicit override.
- Rescheduling changes date/time only; doctor reassignment is a separate reasoned action with history.
- Waiting patients cannot be marked no-show. Linked visits block appointment reassignment and reuse, including cancelled visits.
- Note revisions and voided clinical entries retain history. Finalized examinations have their existing correction workflow.
- Clinic settings allow adjustment of booking spacing and overdue review grace time.
- Doctors can view patients with their own open or closed visits, or active future appointments.

## Limits and evaluation

Use fictional patients for this local evaluation. This release does not implement the entire future V2 design: billing/payments, treatment packages, inventory, automated backups, and clinic deployment hardening remain outside scope. The upgrade creates one pre-upgrade backup, not scheduled backups.

After clinical completion, notes and treatment items are frozen; late amendments for these items are not implemented. Finalized examination corrections remain available. Tooth records are a selector and history, not a graphical diagnosis-status odontogram. Queue updates use the Refresh queue button.

Older visits are marked Legacy. Open legacy visits must choose Quick or Standard before using the new completion rules. Historical closed visits do not imply the new completion checks were performed.

Verified with PHP 8.4 and MariaDB 10.11 in an isolated environment; your Windows PHP 8.2 / MariaDB 10.4 setup remains an on-device acceptance check. See docs/V2_CORE_TEST_REPORT.md.

For a completely new installation only: import database/dental_clinic_setup.sql, create config/database.php from its example with your actual credentials, create the first admin with scripts/create_first_admin.php, then run UPGRADE_V2.cmd. These steps are not needed for your current working demo.
