-- ==============================================================================
-- KODE SQL PERUBAHAN TABEL CLASS_SCHEDULES UNTUK MENDUKUNG PENGARSIPAN JADWAL
-- ==============================================================================

-- 1. Tambahkan kolom tanggal berlaku dan status aktif (jika belum ada)
ALTER TABLE `class_schedules`
  ADD COLUMN IF NOT EXISTS `valid_from` DATE NULL DEFAULT NULL AFTER `day_of_week`,
  ADD COLUMN IF NOT EXISTS `valid_until` DATE NULL DEFAULT NULL AFTER `valid_from`,
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `valid_until`;

-- Set default valid_from untuk data lama yang masih NULL
UPDATE `class_schedules` 
SET `valid_from` = CURRENT_DATE 
WHERE `valid_from` IS NULL;

-- 2. Hapus constraint unik legacy (Penyebab error 1062 Duplicate entry saat pengarsipan)
-- Catatan: Gunakan pengecekan di database jika opsi IF EXISTS didukung (MySQL 8.0+)
-- Jika MySQL 5.7/MariaDB versi lama, jalankan ALTER DROP INDEX secara langsung.
ALTER TABLE `class_schedules` DROP INDEX `uq_class_schedule`;
ALTER TABLE `class_schedules` DROP INDEX `uq_teacher_schedule`;

-- 3. Tambahkan index performa untuk pencarian jadwal aktif berdasarkan rentang tanggal
ALTER TABLE `class_schedules`
  ADD INDEX `idx_schedule_validity` (`academic_year_id`, `grade_level_id`, `day`, `is_active`, `valid_from`, `valid_until`),
  ADD INDEX `idx_teacher_validity` (`academic_year_id`, `employee_id`, `day`, `is_active`, `valid_from`, `valid_until`);
