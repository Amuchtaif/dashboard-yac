<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // Check if status column already exists to avoiding error
    $check = $conn->query("SHOW COLUMNS FROM academic_years LIKE 'status'");
    if ($check->rowCount() == 0) {
        $sql = "ALTER TABLE academic_years 
                ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Inactive' AFTER name,
                ADD COLUMN start_date DATE AFTER status,
                ADD COLUMN end_date DATE AFTER start_date,
                ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"; // Removed AFTER created_at to be safe if created_at is last

        $conn->exec($sql);
        echo "Table altered successfully.\n";
    } else {
        echo "Columns already exist.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>