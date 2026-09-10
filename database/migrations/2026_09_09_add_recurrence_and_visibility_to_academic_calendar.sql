-- Migration: Tambah Kolom Visibilitas dan Pengulangan (Recurrence) pada Academic Calendar
-- Tanggal: 2026-09-09
-- Deskripsi: Menambahkan kolom visibilitas (public/internal) dan kolom pendukung agenda berulang (harian, pekanan, bulanan, tahunan)

-- 1. Pastikan kolom visibility tersedia (enum public/internal)
-- Catatan: Jika kolom sudah ada, lewati perintah ini atau sesuaikan definisi tipe datanya
ALTER TABLE `academic_calendar` 
    ADD COLUMN IF NOT EXISTS `visibility` ENUM('public', 'internal') NOT NULL DEFAULT 'public' AFTER `semester`;

-- 2. Tambahkan kolom agenda berulang (Recurrence)
ALTER TABLE `academic_calendar` 
    ADD COLUMN IF NOT EXISTS `is_recurring` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_holiday`,
    ADD COLUMN IF NOT EXISTS `recurrence_type` ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') NOT NULL DEFAULT 'none' AFTER `is_recurring`,
    ADD COLUMN IF NOT EXISTS `recurrence_rule` TEXT NULL DEFAULT NULL AFTER `recurrence_type`,
    ADD COLUMN IF NOT EXISTS `repeat_group_id` VARCHAR(64) NULL DEFAULT NULL AFTER `recurrence_rule`;

-- 3. Tambahkan Index untuk repeat_group_id guna mempercepat operasi batch & penghapusan rangkaian
ALTER TABLE `academic_calendar`
    ADD INDEX IF NOT EXISTS `idx_repeat_group_id` (`repeat_group_id`);
