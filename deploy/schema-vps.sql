-- Run manually on the SAME database used by the main application's
-- face_enrollment connection. Back up that database first.
-- Existing source tables users and branch_stores are required.
-- No employee records, tokens or enrollment data are inserted or deleted.

CREATE TABLE IF NOT EXISTS face_attendance_employees (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 employee_code VARCHAR(255) NOT NULL UNIQUE,
 source_user_id INT UNSIGNED NULL UNIQUE,
 name VARCHAR(255) NOT NULL,
 department VARCHAR(255) NOT NULL,
 face_embedding JSON NULL,
 face_model VARCHAR(255) NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS face_attendance_records (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 employee_id BIGINT UNSIGNED NOT NULL,
 type ENUM('check_in','check_out') NOT NULL,
 photo_path VARCHAR(255) NOT NULL,
 face_verified TINYINT(1) NOT NULL,
 face_score DOUBLE NULL,
 face_distance DOUBLE NULL,
 attended_at DATETIME NOT NULL,
 attendance_date DATE NOT NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL,
 CONSTRAINT face_attendance_records_employee_id_foreign
   FOREIGN KEY (employee_id) REFERENCES face_attendance_employees(id) ON DELETE CASCADE,
 UNIQUE KEY face_attendance_records_employee_id_type_attendance_date_unique
   (employee_id,type,attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS face_attendance_devices (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 branch_store_id INT UNSIGNED NOT NULL,
 KEY face_attendance_devices_branch_store_id_index (branch_store_id),
 label VARCHAR(100) NULL,
 token_hash VARCHAR(64) NULL UNIQUE,
 expires_at DATETIME NULL,
 activated_at DATETIME NULL,
 pairing_hash VARCHAR(64) NULL UNIQUE,
 pairing_label VARCHAR(100) NULL,
 pairing_expires_at DATETIME NULL,
 owner_id INT UNSIGNED NULL,
 created_at TIMESTAMP NULL,
 updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conditional ALTERs make this usable after some of the manual SQL was applied.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='branch_stores' AND COLUMN_NAME='face_enrollment_enabled'), 'SELECT 1', 'ALTER TABLE branch_stores ADD COLUMN face_enrollment_enabled TINYINT(1) NOT NULL DEFAULT 0');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;

SET @add_branch = NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_records' AND COLUMN_NAME='branch_store_id');
SET @ddl = IF(@add_branch, 'ALTER TABLE face_attendance_records ADD COLUMN branch_store_id INT UNSIGNED NULL, ADD INDEX face_attendance_records_branch_store_id_index (branch_store_id)', 'SELECT 1');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;
-- Backfill only when the snapshot column was first added. Preserve existing snapshots.
SET @ddl = IF(@add_branch, 'UPDATE face_attendance_records a JOIN face_attendance_employees e ON e.id=a.employee_id JOIN users u ON u.id=e.source_user_id SET a.branch_store_id=u.branch_store_id', 'SELECT 1');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;

SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_devices' AND COLUMN_NAME='owner_login_branch_store_id'), 'SELECT 1', 'ALTER TABLE face_attendance_devices ADD COLUMN owner_login_branch_store_id INT UNSIGNED NULL');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_devices' AND COLUMN_NAME='owner_login_branch_name'), 'SELECT 1', 'ALTER TABLE face_attendance_devices ADD COLUMN owner_login_branch_name VARCHAR(100) NULL');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_devices' AND COLUMN_NAME='owner_name'), 'SELECT 1', 'ALTER TABLE face_attendance_devices ADD COLUMN owner_name VARCHAR(255) NULL');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_devices' AND COLUMN_NAME='owner_recorded_at'), 'SELECT 1', 'ALTER TABLE face_attendance_devices ADD COLUMN owner_recorded_at DATETIME NULL');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;

-- Allow multiple devices while preserving all existing rows and tokens.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_devices' AND INDEX_NAME='face_attendance_devices_branch_store_id_index'), 'SELECT 1', 'ALTER TABLE face_attendance_devices ADD INDEX face_attendance_devices_branch_store_id_index (branch_store_id)');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_devices' AND INDEX_NAME='face_attendance_devices_branch_store_id_unique'), 'ALTER TABLE face_attendance_devices DROP INDEX face_attendance_devices_branch_store_id_unique', 'SELECT 1');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_devices' AND INDEX_NAME='branch_store_id' AND NON_UNIQUE=0), 'ALTER TABLE face_attendance_devices DROP INDEX branch_store_id', 'SELECT 1');
PREPARE face_stmt FROM @ddl; EXECUTE face_stmt; DEALLOCATE PREPARE face_stmt;
