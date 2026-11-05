-- Shared attendance / face_enrollment database. Back up before applying.
-- Requires the existing face_attendance_employees profile table.
CREATE TABLE IF NOT EXISTS `face_attendance_samples` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `face_profile_id` BIGINT UNSIGNED NOT NULL,
    `slot` TINYINT UNSIGNED NOT NULL,
    `pose` VARCHAR(20) NOT NULL DEFAULT 'front',
    `face_embedding` JSON NOT NULL,
    `face_model` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `face_attendance_samples_profile_slot_unique` (`face_profile_id`, `slot`),
    CONSTRAINT `face_attendance_samples_face_profile_id_foreign`
        FOREIGN KEY (`face_profile_id`) REFERENCES `face_attendance_employees` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve each existing enrollment as its first sample. Safe to repeat.
INSERT INTO `face_attendance_samples`
    (`face_profile_id`, `slot`, `pose`, `face_embedding`, `face_model`, `created_at`, `updated_at`)
SELECT f.`id`, 1, 'front', f.`face_embedding`, f.`face_model`, f.`created_at`, f.`updated_at`
FROM `face_attendance_employees` AS f
WHERE f.`face_embedding` IS NOT NULL
  AND f.`face_model` IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `face_attendance_samples` AS s WHERE s.`face_profile_id` = f.`id`
  );
