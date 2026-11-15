-- =====================================================================
-- Dental Clinic Management System: complete database setup (fresh install)
-- Target: MariaDB 10.4.x (XAMPP), InnoDB, utf8mb4. Import with phpMyAdmin.
--
-- HOW THIS FILE BEHAVES
--  * It CREATES the database `dental_clinic` if missing, then selects it.
--  * Plain CREATE TABLE (no DROP, no IF NOT EXISTS): if the tables already exist the import
--    stops with an error. It never touches any other database.
--  * Import at the SERVER level in phpMyAdmin (home page > Import). To use another database
--    name, change the two lines below AND config/database.php.
--  * Reference data only (roles). No patients, no users, no passwords.
-- =====================================================================
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS `dental_clinic` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dental_clinic`;

CREATE TABLE schema_version (
    version VARCHAR(20) NOT NULL,
    description VARCHAR(255) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role (
    role_code VARCHAR(20) NOT NULL,
    role_name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (role_code),
    UNIQUE KEY uq_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_account (
    user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    username_key VARCHAR(80) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NULL,
    email_key VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    password_hash VARCHAR(255) NOT NULL,
    account_status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_user_username_key (username_key),
    UNIQUE KEY uq_user_email_key (email_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role grants are kept for history. active_flag is 1 while active and NULL once revoked,
-- so the UNIQUE key allows only ONE active grant of a role per user.
CREATE TABLE user_role (
    user_role_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    role_code VARCHAR(20) NOT NULL,
    active_flag TINYINT(1) NULL DEFAULT 1,
    assigned_by BIGINT UNSIGNED NULL COMMENT 'NULL only for console setup/recovery scripts',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by BIGINT UNSIGNED NULL,
    revoked_at DATETIME NULL,
    PRIMARY KEY (user_role_id),
    UNIQUE KEY uq_user_role_active (user_id, role_code, active_flag),
    KEY ix_user_role_role (role_code),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_code) REFERENCES role (role_code) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_ur_assigned_by FOREIGN KEY (assigned_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_ur_revoked_by FOREIGN KEY (revoked_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE doctor (
    doctor_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    doctor_code VARCHAR(30) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    license_number VARCHAR(80) NULL,
    profile_status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (doctor_id),
    UNIQUE KEY uq_doctor_user (user_id),
    UNIQUE KEY uq_doctor_code (doctor_code),
    CONSTRAINT fk_doctor_user FOREIGN KEY (user_id) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Public requests can only ever ask for STAFF or DOCTOR. The password hash is cleared once decided.
CREATE TABLE account_request (
    request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    applicant_name VARCHAR(160) NOT NULL,
    requested_username VARCHAR(80) NOT NULL,
    requested_username_key VARCHAR(80) NOT NULL,
    requested_email VARCHAR(190) NULL,
    requested_email_key VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    requested_role VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NULL,
    request_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    pending_username_key VARCHAR(80) NULL,
    pending_email_key VARCHAR(190) NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    rejection_reason VARCHAR(500) NULL,
    created_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    UNIQUE KEY uq_req_pending_username (pending_username_key),
    UNIQUE KEY uq_req_pending_email (pending_email_key),
    KEY ix_req_status (request_status, created_at),
    CONSTRAINT chk_req_role CHECK (requested_role IN ('STAFF','DOCTOR')),
    CONSTRAINT fk_req_role FOREIGN KEY (requested_role) REFERENCES role (role_code) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_req_reviewer FOREIGN KEY (reviewed_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_req_created_user FOREIGN KEY (created_user_id) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE patient (
    patient_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_code VARCHAR(30) NOT NULL COMMENT 'registration number, system generated',
    full_name VARCHAR(160) NOT NULL,
    name_key VARCHAR(190) NOT NULL COMMENT 'lower-cased, whitespace-collapsed, for duplicate review',
    date_of_birth DATE NULL,
    gender VARCHAR(40) NULL,
    occupation VARCHAR(120) NULL,
    nationality VARCHAR(80) NULL,
    phone VARCHAR(40) NULL,
    phone_key VARCHAR(40) NULL COMMENT 'digits only, for duplicate review',
    address TEXT NULL,
    nrc_number VARCHAR(80) NULL COMMENT 'optional, never a key',
    registration_date DATE NOT NULL,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    archive_reason VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (patient_id),
    UNIQUE KEY uq_patient_code (patient_code),
    KEY ix_patient_name (name_key),
    KEY ix_patient_phone (phone_key),
    KEY ix_patient_nrc (nrc_number),
    KEY ix_patient_dob (date_of_birth),
    CONSTRAINT chk_patient_dob CHECK (date_of_birth IS NULL OR date_of_birth <= registration_date),
    CONSTRAINT fk_patient_created_by FOREIGN KEY (created_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_patient_updated_by FOREIGN KEY (updated_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_patient_archived_by FOREIGN KEY (archived_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medical history: every save is a NEW version row. Earlier versions are never changed.
CREATE TABLE medical_history_version (
    history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    allergy_status ENUM('NOT_ASKED','UNKNOWN','NONE_REPORTED','HAS_ALLERGIES','NOT_APPLICABLE') NOT NULL DEFAULT 'NOT_ASKED',
    past_medical_history_status ENUM('NOT_ASKED','UNKNOWN','NONE_REPORTED','DETAILS_REPORTED','NOT_APPLICABLE') NOT NULL DEFAULT 'NOT_ASKED',
    past_medical_history TEXT NULL,
    medication_status ENUM('NOT_ASKED','UNKNOWN','NONE_REPORTED','TAKING_MEDICATION','NOT_APPLICABLE') NOT NULL DEFAULT 'NOT_ASKED',
    regular_medication TEXT NULL,
    additional_note TEXT NULL COMMENT 'blank note area on the medical form (OD-05)',
    recorded_by BIGINT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    UNIQUE KEY uq_med_patient_version (patient_id, version_no),
    CONSTRAINT fk_med_patient FOREIGN KEY (patient_id) REFERENCES patient (patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_med_recorded_by FOREIGN KEY (recorded_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE allergy_item (
    allergy_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    history_id BIGINT UNSIGNED NOT NULL,
    allergen_name VARCHAR(180) NOT NULL,
    severity ENUM('UNKNOWN','MILD','MODERATE','SEVERE') NOT NULL DEFAULT 'UNKNOWN',
    reaction_note VARCHAR(500) NULL,
    PRIMARY KEY (allergy_id),
    KEY ix_allergy_history (history_id),
    CONSTRAINT fk_allergy_history FOREIGN KEY (history_id) REFERENCES medical_history_version (history_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dental history questionnaire: one column per question. NOT_PROVIDED is distinct from NO.
CREATE TABLE dental_history_version (
    dental_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    last_dental_visit_text VARCHAR(160) NULL,
    extraction_complications ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    extraction_complication_note TEXT NULL,
    brushing_frequency VARCHAR(80) NULL,
    flosses ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    bleeding_gums ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    bad_breath ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    sensitive_sweet ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    sensitive_hot ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    sensitive_cold ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    tender_chewing ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    bruxism ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    orthodontic_history ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    tmj_pain ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    tmj_clicking ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    smokes_tobacco ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    chews_betel_quid ENUM('YES','NO','UNKNOWN','NOT_PROVIDED') NOT NULL DEFAULT 'NOT_PROVIDED',
    additional_note TEXT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dental_id),
    UNIQUE KEY uq_dental_patient_version (patient_id, version_no),
    CONSTRAINT fk_dental_patient FOREIGN KEY (patient_id) REFERENCES patient (patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_dental_recorded_by FOREIGN KEY (recorded_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointment (
    appointment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_no VARCHAR(30) NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    reason VARCHAR(255) NULL,
    note TEXT NULL,
    status ENUM('SCHEDULED','CONFIRMED','COMPLETED','CANCELLED','NO_SHOW') NOT NULL DEFAULT 'SCHEDULED',
    cancellation_reason VARCHAR(500) NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (appointment_id),
    UNIQUE KEY uq_appointment_no (appointment_no),
    KEY ix_appt_doctor_time (doctor_id, start_at),
    KEY ix_appt_patient_time (patient_id, start_at),
    KEY ix_appt_status_time (status, start_at),
    CONSTRAINT chk_appt_times CHECK (end_at > start_at),
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES patient (patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_appt_doctor FOREIGN KEY (doctor_id) REFERENCES doctor (doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_appt_created_by FOREIGN KEY (created_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_appt_updated_by FOREIGN KEY (updated_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_appt_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only record of reschedules and cancellations (keeps old times and the reason).
CREATE TABLE appointment_change (
    change_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id BIGINT UNSIGNED NOT NULL,
    change_type ENUM('RESCHEDULED','CANCELLED') NOT NULL,
    old_start_at DATETIME NOT NULL,
    old_end_at DATETIME NOT NULL,
    new_start_at DATETIME NULL,
    new_end_at DATETIME NULL,
    reason VARCHAR(500) NULL COMMENT 'administrative reason, not clinical detail',
    changed_by BIGINT UNSIGNED NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (change_id),
    KEY ix_apptchg_appt (appointment_id, changed_at),
    CONSTRAINT fk_apptchg_appt FOREIGN KEY (appointment_id) REFERENCES appointment (appointment_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_apptchg_user FOREIGN KEY (changed_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clinical_visit (
    visit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_no VARCHAR(30) NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL COMMENT 'responsible doctor',
    appointment_id BIGINT UNSIGNED NULL COMMENT 'NULL for walk-in and emergency visits',
    visit_type ENUM('APPOINTMENT','WALK_IN','EMERGENCY') NOT NULL,
    status ENUM('OPEN','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    note TEXT NULL COMMENT 'reception note',
    opened_by BIGINT UNSIGNED NOT NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancel_reason_code VARCHAR(30) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (visit_id),
    UNIQUE KEY uq_visit_no (visit_no),
    UNIQUE KEY uq_visit_appointment (appointment_id),
    KEY ix_visit_patient (patient_id, visited_at),
    KEY ix_visit_doctor (doctor_id, status),
    CONSTRAINT chk_visit_type_link CHECK ((visit_type = 'APPOINTMENT' AND appointment_id IS NOT NULL) OR (visit_type <> 'APPOINTMENT' AND appointment_id IS NULL)),
    CONSTRAINT fk_visit_patient FOREIGN KEY (patient_id) REFERENCES patient (patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_visit_doctor FOREIGN KEY (doctor_id) REFERENCES doctor (doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_visit_appointment FOREIGN KEY (appointment_id) REFERENCES appointment (appointment_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_visit_opened_by FOREIGN KEY (opened_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_visit_closed_by FOREIGN KEY (closed_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_visit_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clinical examination. Code fields hold the exact codes printed on the clinic form (config/clinic_options.php).
-- DRAFT is editable. FINALIZED is never changed except to become SUPERSEDED by a correction row.
CREATE TABLE clinical_examination (
    exam_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visit_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL COMMENT 'responsible doctor of the visit',
    authored_by BIGINT UNSIGNED NOT NULL COMMENT 'actual logged-in user (doctor or admin)',
    status ENUM('DRAFT','FINALIZED','SUPERSEDED','DISCARDED') NOT NULL DEFAULT 'DRAFT',
    examined_at DATETIME NOT NULL,
    general_teeth_condition VARCHAR(30) NULL,
    oral_hygiene VARCHAR(30) NULL,
    gingival_inflammation VARCHAR(30) NULL,
    gingival_recession VARCHAR(30) NULL,
    occlusion_class VARCHAR(30) NULL,
    overbite_code VARCHAR(30) NULL,
    overjet_code VARCHAR(30) NULL,
    malocclusion_note TEXT NULL,
    lipline_code VARCHAR(30) NULL,
    midline_code VARCHAR(30) NULL,
    mouth_opening_mm DECIMAL(6,2) NULL,
    tmj_note TEXT NULL,
    general_note TEXT NULL,
    supersedes_exam_id BIGINT UNSIGNED NULL,
    correction_reason VARCHAR(500) NULL,
    finalized_by BIGINT UNSIGNED NULL,
    finalized_at DATETIME NULL,
    discarded_by BIGINT UNSIGNED NULL,
    discarded_at DATETIME NULL,
    discard_reason_code VARCHAR(30) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (exam_id),
    UNIQUE KEY uq_exam_supersedes (supersedes_exam_id),
    KEY ix_exam_visit (visit_id, status),
    KEY ix_exam_patient (patient_id, examined_at),
    CONSTRAINT chk_exam_mouth CHECK (mouth_opening_mm IS NULL OR mouth_opening_mm >= 0),
    CONSTRAINT chk_exam_correction CHECK (supersedes_exam_id IS NULL OR correction_reason IS NOT NULL),
    CONSTRAINT fk_exam_visit FOREIGN KEY (visit_id) REFERENCES clinical_visit (visit_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_exam_patient FOREIGN KEY (patient_id) REFERENCES patient (patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_exam_doctor FOREIGN KEY (doctor_id) REFERENCES doctor (doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_exam_author FOREIGN KEY (authored_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_exam_supersedes FOREIGN KEY (supersedes_exam_id) REFERENCES clinical_examination (exam_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_exam_finalized_by FOREIGN KEY (finalized_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_exam_discarded_by FOREIGN KEY (discarded_by) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provisional (NOT confirmed) examination fields live here, so they can be added, renamed or removed
-- in config/clinic_options.php without changing the schema.
CREATE TABLE clinical_examination_extra (
    extra_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(60) NOT NULL,
    field_value TEXT NULL,
    PRIMARY KEY (extra_id),
    UNIQUE KEY uq_exam_extra (exam_id, field_key),
    CONSTRAINT fk_extra_exam FOREIGN KEY (exam_id) REFERENCES clinical_examination (exam_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit trail: append-only. `summary` holds only ids, field names, status changes and codes, never clinical text.
CREATE TABLE audit_event (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_user_id BIGINT UNSIGNED NULL COMMENT 'NULL for console scripts and failed logins',
    actor_role VARCHAR(20) NULL,
    action_code VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id BIGINT UNSIGNED NULL,
    outcome ENUM('SUCCESS','DENIED','FAILED') NOT NULL DEFAULT 'SUCCESS',
    request_id VARCHAR(32) NULL,
    ip_address VARCHAR(45) NULL,
    summary VARCHAR(500) NULL,
    PRIMARY KEY (event_id),
    KEY ix_audit_time (occurred_at),
    KEY ix_audit_actor (actor_user_id, occurred_at),
    KEY ix_audit_action (action_code, occurred_at),
    KEY ix_audit_entity (entity_type, entity_id),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES user_account (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reference data
INSERT INTO role (role_code, role_name, description) VALUES
 ('ADMIN','Administrator','Operational superuser: staff and doctor functions plus account, role and audit administration'),
 ('STAFF','Staff','Reception and records: patients, histories, appointments, visits'),
 ('DOCTOR','Doctor','Clinical: read-only histories, examinations');
INSERT INTO schema_version (version, description) VALUES ('1.0.0','Initial schema for Dental Clinic Management System MVP');
