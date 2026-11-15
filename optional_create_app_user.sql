-- OPTIONAL but recommended: a dedicated least-privilege database account (the app should not use root).
-- 1) EDIT the password below (it appears once). 2) Run in phpMyAdmin > SQL as an administrator AFTER dental_clinic_setup.sql.
-- 3) Put the same user and password in config/database.php.
-- History, audit and appointment-change tables are INSERT/SELECT only; nothing is DELETE-able.
-- (MariaDB table-level grants add to database-level ones, so privileges are granted table by table.)
-- This single line creates the user if it is missing AND (re)sets the password if the user already exists,
-- so it is safe to run again. Use letters and digits only in the password (symbols can break config/database.php).
GRANT USAGE ON *.* TO 'dental_app'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
GRANT SELECT ON dental_clinic.role TO 'dental_app'@'localhost';
GRANT SELECT ON dental_clinic.schema_version TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.user_account TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.user_role TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.doctor TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.account_request TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.patient TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.appointment TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.clinical_visit TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.clinical_examination TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic.clinical_examination_extra TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic.medical_history_version TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic.allergy_item TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic.dental_history_version TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic.appointment_change TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic.audit_event TO 'dental_app'@'localhost';
FLUSH PRIVILEGES;
