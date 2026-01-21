<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // 1. Add is_active column if not exists
    $check = $conn->query("SHOW COLUMNS FROM academic_years LIKE 'is_active'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE academic_years ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER end_date");

        // 2. Sync from status if status exists
        $statusCheck = $conn->query("SHOW COLUMNS FROM academic_years LIKE 'status'");
        if ($statusCheck->rowCount() > 0) {
            $conn->exec("UPDATE academic_years SET is_active = 1 WHERE status = 'Active'");
            $conn->exec("ALTER TABLE academic_years DROP COLUMN status");
            echo "Migrated 'status' to 'is_active'.\n";
        } else {
            echo "Column 'is_active' added.\n";
        }
    } else {
        echo "Column 'is_active' already exists.\n";
    }

} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
?>