-- SQL Schema for Halaqah Management
-- Created for: Manajemen Data Halaqah

CREATE TABLE IF NOT EXISTS `halaqah_groups` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `group_name` VARCHAR(255) NOT NULL,
    `teacher_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `teacher_id` (`teacher_id`),
    CONSTRAINT `fk_halaqah_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `halaqah_members` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `group_id` INT(11) NOT NULL,
    `student_id` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `group_id` (`group_id`),
    KEY `student_id` (`student_id`),
    CONSTRAINT `fk_halaqah_group` FOREIGN KEY (`group_id`) REFERENCES `halaqah_groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_halaqah_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
