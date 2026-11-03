-- Migration: Create Guardian (Wali Murid) Portal Tables
-- Date: 2026-11-02

-- 1. Table: guardians (Akun Orang Tua / Wali Murid)
CREATE TABLE IF NOT EXISTS `guardians` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `phone_number` VARCHAR(20) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `relationship` ENUM('Ayah', 'Ibu', 'Wali') NOT NULL DEFAULT 'Ayah',
  `email` VARCHAR(100) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `fcm_token` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_guardians_phone` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: guardian_students (Relasi Many-to-Many Wali dengan Santri/Siswa)
CREATE TABLE IF NOT EXISTS `guardian_students` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `guardian_id` INT(11) NOT NULL,
  `student_id` INT(11) NOT NULL,
  `relationship` ENUM('Ayah', 'Ibu', 'Wali') NOT NULL DEFAULT 'Ayah',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Wali kontak utama',
  `is_verified` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Sudah terverifikasi sekolah',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_guardian_student` (`guardian_id`, `student_id`),
  KEY `idx_guardian_id` (`guardian_id`),
  KEY `idx_student_id` (`student_id`),
  CONSTRAINT `fk_guardian_students_guardian` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_guardian_students_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: guardian_otps (Log Verifikasi OTP / Aktivasi Akun Wali)
CREATE TABLE IF NOT EXISTS `guardian_otps` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `phone_number` VARCHAR(20) NOT NULL,
  `otp_code` VARCHAR(10) NOT NULL,
  `action` ENUM('activation', 'login', 'reset_pin') NOT NULL DEFAULT 'activation',
  `expires_at` DATETIME NOT NULL,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`phone_number`, `otp_code`, `is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

