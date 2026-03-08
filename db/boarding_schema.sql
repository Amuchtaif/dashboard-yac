-- SQL Schema for Boarding (Kepengasuhan) Management
-- Created for: YAC Boarding Management Module

-- 1. Boarding Rooms (Data Asrama)
CREATE TABLE IF NOT EXISTS `boarding_rooms` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `room_name` VARCHAR(255) NOT NULL,
    `supervisor_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supervisor_id` (`supervisor_id`),
    CONSTRAINT `fk_boarding_room_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Boarding Room Members (Santri per Asrama)
CREATE TABLE IF NOT EXISTS `boarding_room_members` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `room_id` INT(11) NOT NULL,
    `student_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `room_id` (`room_id`),
    KEY `student_id` (`student_id`),
    CONSTRAINT `fk_boarding_room_id` FOREIGN KEY (`room_id`) REFERENCES `boarding_rooms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_boarding_student_id` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Violation Types (Jenis Pelanggaran)
CREATE TABLE IF NOT EXISTS `boarding_violation_types` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `type_name` VARCHAR(255) NOT NULL,
    `points` INT(11) DEFAULT 0,
    `category` ENUM('Ringan', 'Sedang', 'Berat') NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Student Violations (Kelola Pelanggaran)
CREATE TABLE IF NOT EXISTS `boarding_violations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `type_id` INT(11) NOT NULL,
    `date` DATE NOT NULL,
    `description` TEXT,
    `reporter_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `student_id` (`student_id`),
    KEY `type_id` (`type_id`),
    KEY `reporter_id` (`reporter_id`),
    CONSTRAINT `fk_violation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_violation_type` FOREIGN KEY (`type_id`) REFERENCES `boarding_violation_types` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_violation_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Boarding Holidays (Kelola Libur)
CREATE TABLE IF NOT EXISTS `boarding_holidays` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('Aktif', 'Selesai') DEFAULT 'Aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Student Returns (Kelola Kepulangan Santri)
CREATE TABLE IF NOT EXISTS `boarding_returns` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `return_date` DATE NOT NULL,
    `status` ENUM('Sudah Kembali', 'Belum Kembali') DEFAULT 'Belum Kembali',
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `student_id` (`student_id`),
    CONSTRAINT `fk_return_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Student Permits (Kelola Izin Santri)
CREATE TABLE IF NOT EXISTS `boarding_permits` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `status` ENUM('Menunggu', 'Disetujui', 'Ditolak', 'Kembali') DEFAULT 'Menunggu',
    `approved_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `student_id` (`student_id`),
    KEY `approved_by` (`approved_by`),
    CONSTRAINT `fk_permit_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_permit_approver` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
