-- Run on the shared attendance / face_enrollment database, after a backup.
-- employees starts empty. No user/trainer import or historical profile relinking.
CREATE TABLE IF NOT EXISTS `employees` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_code` VARCHAR(50) NOT NULL,
    `branch_store_id` INT UNSIGNED NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `position` VARCHAR(100) NULL,
    `phone` VARCHAR(30) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employees_employee_code_unique` (`employee_code`),
    KEY `employees_branch_store_id_index` (`branch_store_id`),
    KEY `employees_attendance_scope_index` (`branch_store_id`, `is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fresh databases also need the face-profile table.
CREATE TABLE IF NOT EXISTS `face_attendance_employees` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_code` VARCHAR(255) NOT NULL,
    `source_user_id` INT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `department` VARCHAR(255) NOT NULL,
    `face_embedding` JSON NULL,
    `face_model` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `face_attendance_employees_employee_code_unique` (`employee_code`),
    UNIQUE KEY `face_attendance_employees_source_user_id_unique` (`source_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing profiles are preserved. Add the link only if missing.
SET @employee_link_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='face_attendance_employees'
        AND COLUMN_NAME='source_employee_id'),
    'SELECT 1',
    'ALTER TABLE face_attendance_employees ADD COLUMN source_employee_id BIGINT UNSIGNED NULL, ADD UNIQUE KEY face_attendance_employees_source_employee_id_unique (source_employee_id)'
);
PREPARE employee_link_stmt FROM @employee_link_ddl;
EXECUTE employee_link_stmt;
DEALLOCATE PREPARE employee_link_stmt;
