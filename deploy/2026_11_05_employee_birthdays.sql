-- Run on the shared attendance / face_enrollment database.
-- Requires employees; skip this ALTER if born already exists.
ALTER TABLE `employees` ADD COLUMN `born` DATE NULL;
