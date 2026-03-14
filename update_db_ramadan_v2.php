<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // Check if column exists in ramadan_settings
    $stmt = $conn->query("SHOW COLUMNS FROM ramadan_settings LIKE 'half_day_start_time'");
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE ramadan_settings ADD COLUMN half_day_start_time TIME DEFAULT '08:00:00'");
    }

    echo "Database updated successfully.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
