-- Run on the shared FACE database used by both Master and Face Attendance.
-- Existing face_attendance_records, employees and face_attendance_employees are required.
-- Run this SQL once, OR run either application's new migration.
CREATE TABLE IF NOT EXISTS employee_shift_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_store_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  start_time TIME NOT NULL, end_time TIME NOT NULL,
  grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX employee_shift_templates_branch_store_id_index (branch_store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_shift_schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL, branch_store_id INT UNSIGNED NOT NULL,
  work_date DATE NOT NULL, template_id BIGINT UNSIGNED NULL,
  shift_name VARCHAR(100) NOT NULL, starts_at DATETIME NULL, ends_at DATETIME NULL,
  grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_day_off TINYINT(1) NOT NULL DEFAULT 0, note TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY employee_shift_employee_date_unique (employee_id,work_date),
  INDEX employee_shift_branch_date_index (branch_store_id,work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_attendance_leaves (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id BIGINT UNSIGNED NOT NULL, branch_store_id INT UNSIGNED NOT NULL,
  type VARCHAR(20) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL,
  reason TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_by INT UNSIGNED NOT NULL, reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL, review_note TEXT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX employee_leave_dates_index (employee_id,start_date,end_date),
  INDEX employee_leave_branch_status_index (branch_store_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- This ALTER is for installations that have not run the migration/SQL yet.
ALTER TABLE face_attendance_records
  ADD COLUMN schedule_id BIGINT UNSIGNED NULL,
  ADD COLUMN scheduled_start DATETIME NULL,
  ADD COLUMN scheduled_end DATETIME NULL,
  ADD COLUMN late_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN early_leave_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  ADD INDEX face_attendance_records_schedule_id_index (schedule_id);
