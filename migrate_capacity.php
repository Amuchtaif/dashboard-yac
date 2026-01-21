<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $conn->exec("ALTER TABLE grade_levels ADD COLUMN capacity INT NOT NULL DEFAULT 36 COMMENT 'Maximum number of students'");
    echo "Column 'capacity' added successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'capacity' already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>