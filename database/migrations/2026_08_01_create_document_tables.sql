-- database/migrations/2026_08_01_create_document_tables.sql

-- 1. Create document_templates table
CREATE TABLE IF NOT EXISTS `document_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `content_template` TEXT NOT NULL,
    `number_format` VARCHAR(100) NOT NULL DEFAULT '{nomor}/{unit}/{bulan_romawi}/{tahun}',
    `counter` INT NOT NULL DEFAULT 1,
    `reset_cycle` ENUM('monthly', 'yearly', 'never') NOT NULL DEFAULT 'yearly',
    `last_reset_date` DATE DEFAULT NULL,
    `workflow_stages` TEXT NOT NULL, -- JSON list of position/role names or levels
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create documents table
CREATE TABLE IF NOT EXISTS `documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `creator_id` INT NOT NULL,
    `template_id` INT DEFAULT NULL,
    `type` ENUM('outgoing', 'incoming') NOT NULL DEFAULT 'outgoing',
    `document_number` VARCHAR(100) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT DEFAULT NULL,
    `placeholder_values` TEXT DEFAULT NULL, -- JSON format values
    `sender` VARCHAR(255) DEFAULT NULL, -- for incoming
    `receiver_department_id` INT DEFAULT NULL, -- destination department/bidang
    `receiver_unit_id` INT DEFAULT NULL, -- destination unit
    `file_path` VARCHAR(255) DEFAULT NULL, -- for incoming attachment or generated PDF
    `qr_token` VARCHAR(100) NOT NULL UNIQUE,
    `status` ENUM('draft', 'pending_approval', 'approved', 'rejected', 'completed', 'archived') NOT NULL DEFAULT 'draft',
    `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_creator` (`creator_id`),
    KEY `idx_template` (`template_id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create document_approvals table
CREATE TABLE IF NOT EXISTS `document_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `document_id` INT NOT NULL,
    `stage_order` INT NOT NULL,
    `approver_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_document` (`document_id`),
    KEY `idx_approver` (`approver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create document_dispositions table
CREATE TABLE IF NOT EXISTS `document_dispositions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `document_id` INT NOT NULL,
    `from_user_id` INT NOT NULL,
    `to_user_id` INT NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `deadline` DATE DEFAULT NULL,
    `status` ENUM('pending', 'read', 'completed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_document` (`document_id`),
    KEY `idx_from` (`from_user_id`),
    KEY `idx_to` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Add can_access_documents column to positions table if not exists
SET @dbname = DATABASE();
SET @tablename = 'positions';
SET @columnname = 'can_access_documents';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE `positions` ADD COLUMN `can_access_documents` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_manage_assignments`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Add signature_path column to employees table if not exists
SET @tablename_emp = 'employees';
SET @columnname_sig = 'signature_path';
SET @preparedStatement_emp = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename_emp AND COLUMN_NAME = @columnname_sig) > 0,
    'SELECT 1',
    'ALTER TABLE `employees` ADD COLUMN `signature_path` VARCHAR(255) DEFAULT NULL AFTER `profile_photo`'
));
PREPARE stmt_emp FROM @preparedStatement_emp;
EXECUTE stmt_emp;
DEALLOCATE PREPARE stmt_emp;
