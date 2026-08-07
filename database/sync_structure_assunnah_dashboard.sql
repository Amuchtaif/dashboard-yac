-- =================================================================================
-- SQL MIGRATION: SINKRONISASI STRUKTUR DATABASE PRODUCTION/DEPLOY (assunnah_dashboard)
-- Tanggal Dibuat: 2026-08-07
-- Deskripsi: Menyamakan struktur database deploy (assunnah_dashboard) dengan DB lokal
--            Termasuk Modul Tahfidz & Modul Surat Digital (Hanya Struktur Tanpa Data).
-- =================================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------------
-- 1. PEMBUATAN TABEL BARU YANG BELUM ADA DI DEPLOY (7 TABEL)
-- ---------------------------------------------------------------------------------

-- A. MODUL SURAT DIGITAL / DOKUMENTASI
-- 1. Tabel: document_templates (Template Surat & Kop Surat)
CREATE TABLE IF NOT EXISTS `document_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `content_template` TEXT NOT NULL,
  `number_format` VARCHAR(100) NOT NULL DEFAULT '{nomor}/{unit}/{bulan_romawi}/{tahun}',
  `counter` INT(11) NOT NULL DEFAULT 1,
  `reset_cycle` ENUM('monthly','yearly','never') NOT NULL DEFAULT 'yearly',
  `last_reset_date` DATE DEFAULT NULL,
  `workflow_stages` TEXT NOT NULL,
  `header_logo` VARCHAR(255) DEFAULT 'uploads/kop_logos/logo_yac.png',
  `header_line_1` VARCHAR(255) DEFAULT 'YAYASAN AS SUNNAH CIREBON',
  `header_line_2` VARCHAR(255) DEFAULT 'BIDANG PENDIDIKAN',
  `header_address` TEXT DEFAULT 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135',
  `header_image` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Tabel: documents (Dokumen Surat Masuk / Keluar)
CREATE TABLE IF NOT EXISTS `documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `creator_id` INT(11) NOT NULL,
  `template_id` INT(11) DEFAULT NULL,
  `type` ENUM('outgoing','incoming') NOT NULL DEFAULT 'outgoing',
  `document_number` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT DEFAULT NULL,
  `placeholder_values` TEXT DEFAULT NULL,
  `sender` VARCHAR(255) DEFAULT NULL,
  `receiver_division_id` INT(11) DEFAULT NULL,
  `receiver_department_id` INT(11) DEFAULT NULL,
  `receiver_unit_id` INT(11) DEFAULT NULL,
  `file_path` VARCHAR(255) DEFAULT NULL,
  `qr_token` VARCHAR(100) NOT NULL,
  `status` ENUM('draft','pending_approval','approved','rejected','completed','archived') NOT NULL DEFAULT 'draft',
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_number` (`document_number`),
  UNIQUE KEY `qr_token` (`qr_token`),
  KEY `idx_creator` (`creator_id`),
  KEY `idx_template` (`template_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_receiver_div` (`receiver_division_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Tabel: document_approvals (Persetujuan / Alur Approval Surat)
CREATE TABLE IF NOT EXISTS `document_approvals` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `document_id` INT(11) NOT NULL,
  `stage_order` INT(11) NOT NULL,
  `approver_id` INT(11) NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_document` (`document_id`),
  KEY `idx_approver` (`approver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Tabel: document_dispositions (Disposisi Surat)
CREATE TABLE IF NOT EXISTS `document_dispositions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `document_id` INT(11) NOT NULL,
  `from_user_id` INT(11) NOT NULL,
  `to_user_id` INT(11) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `deadline` DATE DEFAULT NULL,
  `status` ENUM('pending','read','completed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_document` (`document_id`),
  KEY `idx_from` (`from_user_id`),
  KEY `idx_to` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Tabel: document_routing_rules (Aturan Pengaturan Rute Surat)
CREATE TABLE IF NOT EXISTS `document_routing_rules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `division_id` INT(11) NOT NULL,
  `unit_id` INT(11) DEFAULT NULL,
  `employee_id` INT(11) NOT NULL,
  `role_type` ENUM('handler','approver') NOT NULL DEFAULT 'handler',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_div_unit` (`division_id`,`unit_id`),
  KEY `idx_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- B. MODUL TAHFIDZ
-- 6. Tabel: position_tahfidz_units (Akses Unit Tahfidz per Jabatan)
CREATE TABLE IF NOT EXISTS `position_tahfidz_units` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `position_id` INT(11) NOT NULL,
  `unit_name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pos_unit` (`position_id`,`unit_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Tabel: user_tahfidz_units (Akses Unit Tahfidz per User/Pegawai)
CREATE TABLE IF NOT EXISTS `user_tahfidz_units` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employee_id` INT(11) NOT NULL,
  `unit_name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_emp_unit` (`employee_id`,`unit_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------------------
-- 2. PENAMBAHAN KOLOM PADA TABEL EXISTING
-- ---------------------------------------------------------------------------------

-- Penambahan Kolom Tanda Tangan Digital pada tabel `employees`
ALTER TABLE `employees`
  ADD COLUMN `signature_path` VARCHAR(255) DEFAULT NULL AFTER `profile_photo`;

-- Penambahan Kolom Hak Akses pada tabel `positions`
ALTER TABLE `positions`
  ADD COLUMN `can_access_tahfidz_monitoring` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_access_tahfidz`,
  ADD COLUMN `can_access_documents` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_manage_assignments`;

SET FOREIGN_KEY_CHECKS = 1;
