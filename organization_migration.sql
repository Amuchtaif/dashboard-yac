-- Migration for Organization Structure Refactor

-- 1. Create Divisions Table
CREATE TABLE IF NOT EXISTS `divisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `schedule_id` int(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Modify Units Table (Add schedule_id and change department_id to division_id)
-- Note: Since we are migrating from Departments -> Divisions, we treat existing Departments as Divisions for now or clear them.
-- Strategy: Drop old foreign key, rename column, add new foreign key.

ALTER TABLE `units` DROP FOREIGN KEY `units_ibfk_1`;
-- If you want to migrate data: UPDATE units SET department_id = (SELECT id FROM divisions WHERE ...)
-- For this task, we assume fresh structure or compatible IDs.
ALTER TABLE `units` CHANGE `department_id` `division_id` int(11) NOT NULL;
ALTER TABLE `units` ADD COLUMN `schedule_id` int(11) DEFAULT 1;
ALTER TABLE `units` ADD CONSTRAINT `units_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE;

-- 3. Create Positions Table
CREATE TABLE IF NOT EXISTS `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `level` int(11) NOT NULL, -- 1=Top, 5=Low
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Update Employees Table
ALTER TABLE `employees` DROP FOREIGN KEY `employees_ibfk_1`; -- Drop department FK
ALTER TABLE `employees` CHANGE `department_id` `division_id` int(11) DEFAULT NULL;
ALTER TABLE `employees` ADD COLUMN `position_id` int(11) DEFAULT NULL;
ALTER TABLE `employees` ADD COLUMN `schedule_id` int(11) DEFAULT NULL;

-- Re-add FK for Division
ALTER TABLE `employees` ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL;

-- 5. Seed Data (Optional - specific to user request context)
INSERT INTO `divisions` (`name`) VALUES 
('Pendidikan'), 
('Ekonomi'), 
('Sosial'), 
('Keuangan');

INSERT INTO `positions` (`name`, `level`) VALUES 
('Mudir', 1), 
('Kepala Bidang', 2), 
('Kepala Unit', 3), 
('Guru', 4), 
('Staf', 5);

-- Update existing units to point to a valid division (e.g., ID 1) to prevent FK errors if data exists
-- UPDATE `units` SET `division_id` = 1;
