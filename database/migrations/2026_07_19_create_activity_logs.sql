-- database/migrations/2026_07_19_create_activity_logs.sql
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT DEFAULT NULL,
    `user_name` VARCHAR(255) DEFAULT NULL,
    `role` VARCHAR(255) DEFAULT NULL,
    `module` VARCHAR(100) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100) DEFAULT NULL,
    `record_id` BIGINT DEFAULT NULL,
    `description` TEXT NOT NULL,
    `old_data` JSON DEFAULT NULL,
    `new_data` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `browser` VARCHAR(255) DEFAULT NULL,
    `url` VARCHAR(255) DEFAULT NULL,
    `level` VARCHAR(50) DEFAULT 'INFO',
    `created_at` DATETIME NOT NULL,
    KEY `idx_user_id` (`user_id`),
    KEY `idx_module` (`module`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
