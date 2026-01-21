<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Starting Schema Update...\n";

try {
    // 1. Create positions table
    $sql = "CREATE TABLE IF NOT EXISTS `positions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `level` int(11) NOT NULL COMMENT '1=Director, 2=Head of Division, 3=Head of Unit, 4=Staff',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql);
    echo "Table 'positions' created/checked.\n";

    // 2. Insert default positions if empty
    $stmt = $conn->query("SELECT COUNT(*) FROM positions");
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("INSERT INTO positions (name, level) VALUES 
            ('Director', 1),
            ('Head of Division', 2),
            ('Head of Unit', 3),
            ('Staff', 4)");
        echo "Default positions inserted.\n";
    }

    // 3. Add position_id to employees if not exists
    $stmt = $conn->query("SHOW COLUMNS FROM employees LIKE 'position_id'");
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE employees ADD COLUMN position_id int(11) AFTER unit_id");
        $conn->exec("ALTER TABLE employees ADD CONSTRAINT `employees_position_fk` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL");
        echo "Column 'position_id' added to 'employees'.\n";

        // Update Admin to be Director (Level 1) for testing
        $conn->exec("UPDATE employees SET position_id = 1 WHERE id = 1");
        echo "Admin user updated to Position ID 1 (Director).\n";
    }

    // 4. Update permits table
    $columns = [
        'attachment' => "varchar(255) DEFAULT NULL AFTER reason",
        'approver_id' => "int(11) DEFAULT NULL AFTER status",
        'approved_by' => "int(11) DEFAULT NULL AFTER approver_id",
        'rejection_note' => "text DEFAULT NULL AFTER approved_by"
    ];

    foreach ($columns as $col => $def) {
        $stmt = $conn->query("SHOW COLUMNS FROM permits LIKE '$col'");
        if (!$stmt->fetch()) {
            $conn->exec("ALTER TABLE permits ADD COLUMN $col $def");
            echo "Column '$col' added to 'permits'.\n";
        }
    }

    // Add Foreign Key for approver_id
    // Check if constraint exists first? Easier to just try-catch or skip if column existed.
    // For simplicity, we assume if column was just added, we add FK. 
    // But since this script might run multiple times, let's just try adding FK safely or ignore error.
    try {
        $conn->exec("ALTER TABLE permits ADD CONSTRAINT `permits_approver_fk` FOREIGN KEY (`approver_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL");
        echo "FK for approver_id added.\n";
    } catch (Exception $e) {
        // FK likely exists
    }

    echo "Schema Update Completed Successfully.\n";

} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
?>