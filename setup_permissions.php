<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Setting up Permissions Infrastructure...\n";

// 1. Create table 'user_permissions' (Exception table)
$sql = "CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `permission_name` VARCHAR(50) NOT NULL, -- e.g. 'access_tahfidz', 'create_meeting'
    `is_allowed` TINYINT(1) NOT NULL DEFAULT 1, -- 1 = Allow, 0 = Deny (Override)
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_permission` (`employee_id`, `permission_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    $conn->exec($sql);
    echo "Table 'user_permissions' created.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}

// 2. Add 'can_create_meeting' column to 'positions' if not exists
try {
    $checkCol = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_create_meeting'");
    if ($checkCol->rowCount() == 0) {
        $conn->exec("ALTER TABLE positions ADD COLUMN can_create_meeting TINYINT(1) NOT NULL DEFAULT 0");
        echo "Column 'can_create_meeting' added to 'positions'.\n";
    } else {
        echo "Column 'can_create_meeting' already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error altering table: " . $e->getMessage() . "\n";
}

echo "Setup Complete.";
?>
