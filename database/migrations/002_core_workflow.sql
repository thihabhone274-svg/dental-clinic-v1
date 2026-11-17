-- Additive MariaDB 10.4 migration. Existing records remain LEGACY until reviewed.
ALTER TABLE clinical_visit ADD COLUMN IF NOT EXISTS workflow_mode VARCHAR(12) NOT NULL DEFAULT 'LEGACY';
ALTER TABLE clinical_visit ADD COLUMN IF NOT EXISTS clinical_status VARCHAR(16) NOT NULL DEFAULT 'IN_PROGRESS';
ALTER TABLE clinical_visit ADD COLUMN IF NOT EXISTS administrative_status VARCHAR(16) NOT NULL DEFAULT 'PENDING';
ALTER TABLE clinical_visit ADD COLUMN IF NOT EXISTS clinical_completed_by BIGINT UNSIGNED NULL;
ALTER TABLE clinical_visit ADD COLUMN IF NOT EXISTS clinical_completed_at DATETIME NULL;
ALTER TABLE clinical_visit ADD COLUMN IF NOT EXISTS administrative_completed_by BIGINT UNSIGNED NULL;
ALTER TABLE clinical_visit ADD COLUMN IF NOT EXISTS administrative_completed_at DATETIME NULL;
ALTER TABLE appointment_change MODIFY COLUMN change_type ENUM('RESCHEDULED','CANCELLED','REASSIGNED') NOT NULL;
ALTER TABLE appointment_change ADD COLUMN IF NOT EXISTS old_doctor_id BIGINT UNSIGNED NULL;
ALTER TABLE appointment_change ADD COLUMN IF NOT EXISTS new_doctor_id BIGINT UNSIGNED NULL;
CREATE TABLE IF NOT EXISTS arrival_queue_entry (
 arrival_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 patient_id BIGINT UNSIGNED NOT NULL,
 appointment_id BIGINT UNSIGNED NULL,
 expected_doctor_id BIGINT UNSIGNED NULL,
 origin ENUM('APPOINTMENT','WALK_IN','EMERGENCY') NOT NULL,
 status ENUM('WAITING','CLAIMED','CANCELLED') NOT NULL DEFAULT 'WAITING',
 active_flag TINYINT NULL DEFAULT 1,
 reception_note VARCHAR(2000) NULL,
 arrived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 recorded_by BIGINT UNSIGNED NOT NULL,
 claimed_by BIGINT UNSIGNED NULL,
 claimed_at DATETIME NULL,
 visit_id BIGINT UNSIGNED NULL,
 cancelled_by BIGINT UNSIGNED NULL,
 cancelled_at DATETIME NULL,
 cancel_reason VARCHAR(500) NULL,
 UNIQUE KEY uq_waiting_patient (patient_id,active_flag),
 UNIQUE KEY uq_arrival_visit (visit_id),
 KEY ix_arrival_status (status,arrived_at),
 FOREIGN KEY (patient_id) REFERENCES patient(patient_id),
 FOREIGN KEY (appointment_id) REFERENCES appointment(appointment_id),
 FOREIGN KEY (expected_doctor_id) REFERENCES doctor(doctor_id),
 FOREIGN KEY (recorded_by) REFERENCES user_account(user_id),
 FOREIGN KEY (claimed_by) REFERENCES user_account(user_id),
 FOREIGN KEY (cancelled_by) REFERENCES user_account(user_id),
 FOREIGN KEY (visit_id) REFERENCES clinical_visit(visit_id),
 CHECK ((status='WAITING' AND active_flag=1) OR (status<>'WAITING' AND active_flag IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS visit_note_revision (
 revision_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 visit_id BIGINT UNSIGNED NOT NULL,
 note TEXT NOT NULL,
 authored_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (visit_id) REFERENCES clinical_visit(visit_id),
 FOREIGN KEY (authored_by) REFERENCES user_account(user_id),
 KEY ix_note_visit (visit_id,revision_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS clinical_item (
 item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 visit_id BIGINT UNSIGNED NOT NULL,
 item_type ENUM('FINDING','RECOMMENDATION','PROCEDURE') NOT NULL,
 teeth VARCHAR(160) NULL,
 surfaces VARCHAR(30) NULL,
 description VARCHAR(2000) NOT NULL,
 recorded_by BIGINT UNSIGNED NOT NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 voided_by BIGINT UNSIGNED NULL,
 voided_at DATETIME NULL,
 void_reason VARCHAR(500) NULL,
 FOREIGN KEY (visit_id) REFERENCES clinical_visit(visit_id),
 FOREIGN KEY (recorded_by) REFERENCES user_account(user_id),
 FOREIGN KEY (voided_by) REFERENCES user_account(user_id),
 KEY ix_item_visit (visit_id,item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS clinic_setting (
 setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (updated_by) REFERENCES user_account(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
