<?php
// database/migrations/run_create_routing_rules.php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $conn->exec("
        CREATE TABLE IF NOT EXISTS `document_routing_rules` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `division_id` INT NOT NULL,
            `unit_id` INT DEFAULT NULL,
            `employee_id` INT NOT NULL,
            `role_type` ENUM('handler', 'approver') NOT NULL DEFAULT 'handler',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_div_unit` (`division_id`, `unit_id`),
            KEY `idx_emp` (`employee_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "SUCCESS: Table document_routing_rules created or verified successfully.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
