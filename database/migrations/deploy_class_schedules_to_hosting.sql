-- ==============================================================================
-- DEPLOY SQL: Perubahan Tabel class_schedules untuk Hosting
-- Tanggal: 2026-08-17
-- Deskripsi: Menambahkan fitur pengarsipan jadwal (valid_from, valid_until, is_active)
--            dan menghapus unique key legacy yang konflik.
-- ==============================================================================
-- INSTRUKSI:
--   1. Backup database hosting terlebih dahulu!
--   2. Jalankan SQL ini di phpMyAdmin hosting atau via command line.
--   3. Pastikan semua query berhasil tanpa error.
-- ==============================================================================

-- ============================================
-- LANGKAH 1: Tambah kolom baru untuk pengarsipan
-- ============================================
ALTER TABLE `class_schedules`
  ADD COLUMN IF NOT EXISTS `valid_from` DATE NULL DEFAULT NULL AFTER `day_of_week`,
  ADD COLUMN IF NOT EXISTS `valid_until` DATE NULL DEFAULT NULL AFTER `valid_from`,
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `valid_until`;

-- ============================================
-- LANGKAH 2: Isi kolom valid_from untuk data lama yang masih NULL
-- ============================================
UPDATE `class_schedules` 
SET `valid_from` = '2026-07-13' 
WHERE `valid_from` = '2026-08-17';

-- ============================================
-- LANGKAH 3: Hapus unique key legacy (penyebab error duplikat saat arsip)
-- Catatan: Jika hosting pakai MySQL 5.7 yang tidak support IF EXISTS,
--          cek dulu apakah index ada via phpMyAdmin sebelum menjalankan.
-- ============================================
-- Untuk MySQL 8.0+ / MariaDB 10.1.4+:
ALTER TABLE `class_schedules` DROP INDEX IF EXISTS `uq_class_schedule`;
ALTER TABLE `class_schedules` DROP INDEX IF EXISTS `uq_teacher_schedule`;

-- Alternatif untuk MySQL 5.7 (uncomment jika perlu, comment baris di atas):
-- ALTER TABLE `class_schedules` DROP INDEX `uq_class_schedule`;
-- ALTER TABLE `class_schedules` DROP INDEX `uq_teacher_schedule`;

-- ============================================
-- LANGKAH 4: Tambah index performa baru untuk pencarian jadwal
-- ============================================
ALTER TABLE `class_schedules`
  ADD INDEX `idx_schedule_validity` (`academic_year_id`, `grade_level_id`, `day`, `is_active`, `valid_from`, `valid_until`),
  ADD INDEX `idx_teacher_validity` (`academic_year_id`, `employee_id`, `day`, `is_active`, `valid_from`, `valid_until`);

-- ============================================
-- SELESAI! Verifikasi dengan menjalankan:
-- ============================================
-- DESCRIBE `class_schedules`;
-- SHOW INDEX FROM `class_schedules`;
