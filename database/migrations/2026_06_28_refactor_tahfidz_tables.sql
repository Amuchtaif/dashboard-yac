-- Migration: Refactor Tahfidz Tables (Baselines, Snapshots, Entries)
-- Date: 2026-06-28

-- 1. Create memorization_baselines table
CREATE TABLE IF NOT EXISTS `memorization_baselines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id` INT(11) NOT NULL,
  `student_id` INT(11) NOT NULL,
  `baseline_juz` DECIMAL(5,2) NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_acad_student` (`academic_year_id`, `student_id`),
  CONSTRAINT `fk_baseline_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create semester_snapshots table
CREATE TABLE IF NOT EXISTS `semester_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id` INT(11) NOT NULL,
  `semester` ENUM('Ganjil', 'Genap') NOT NULL,
  `student_id` INT(11) NOT NULL,
  `baseline_juz` DECIMAL(5,2) NOT NULL,
  `target_juz` DECIMAL(5,2) NOT NULL,
  `memorized_juz` DECIMAL(5,2) NOT NULL,
  `total_juz` DECIMAL(5,2) NOT NULL,
  `murojaah_total` INT(11) DEFAULT 0,
  `tasmi_score` DECIMAL(5,2) DEFAULT 0.00,
  `progress_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `notes` TEXT NULL,
  `generated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_snapshot` (`academic_year_id`, `semester`, `student_id`),
  CONSTRAINT `fk_snapshot_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create memorization_entries table (refactored version of tahfidz_memorization)
CREATE TABLE IF NOT EXISTS `memorization_entries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `date` DATE NOT NULL,
  `entry_type` ENUM('HAFALAN_BARU', 'MUROJAAH', 'TASMI', 'UJIAN') NOT NULL,
  `start_surah_id` INT(11) DEFAULT NULL,
  `start_ayah` INT(11) DEFAULT NULL,
  `end_surah_id` INT(11) DEFAULT NULL,
  `end_ayah` INT(11) DEFAULT NULL,
  `line_count` INT(11) DEFAULT 0,
  `score` DECIMAL(5,2) DEFAULT NULL,
  `notes` TEXT NULL,
  `teacher_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Compatibility Columns
  `surah_id` INT(11) DEFAULT NULL,
  `surah_start` VARCHAR(100) DEFAULT NULL,
  `surah_end` VARCHAR(100) DEFAULT NULL,
  `total_baris` INT(11) DEFAULT 0,
  `juz` INT(11) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_entry_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
