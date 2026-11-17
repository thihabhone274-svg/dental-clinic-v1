-- OPTIONAL but recommended: a dedicated least-privilege database account (the app should not use root).
-- 1) EDIT the password below (it appears once). 2) Run in phpMyAdmin > SQL as an administrator AFTER dental_clinic_v2_demo_setup.sql.
-- 3) Put the same user and password in config/database.php.
-- History, audit and appointment-change tables are INSERT/SELECT only; nothing is DELETE-able.
-- (MariaDB table-level grants add to database-level ones, so privileges are granted table by table.)
-- This single line creates the user if it is missing AND (re)sets the password if the user already exists,
-- so it is safe to run again. Use letters and digits only in the password (symbols can break config/database.php).
GRANT USAGE ON *.* TO 'dental_app'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
GRANT SELECT ON dental_clinic_v2_demo.role TO 'dental_app'@'localhost';
GRANT SELECT ON dental_clinic_v2_demo.schema_version TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.user_account TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.user_role TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.doctor TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.account_request TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.patient TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.appointment TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.clinical_visit TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.clinical_examination TO 'dental_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON dental_clinic_v2_demo.clinical_examination_extra TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic_v2_demo.medical_history_version TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic_v2_demo.allergy_item TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic_v2_demo.dental_history_version TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic_v2_demo.appointment_change TO 'dental_app'@'localhost';
GRANT SELECT, INSERT ON dental_clinic_v2_demo.audit_event TO 'dental_app'@'localhost';
FLUSH PRIVILEGES;
