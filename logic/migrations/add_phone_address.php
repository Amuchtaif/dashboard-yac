<?php
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // Add phone_number column
    $conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) DEFAULT NULL AFTER email");
    echo "Added phone_number column.<br>";

    // Add address column
    $conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL AFTER phone_number");
    echo "Added address column.<br>";

    echo "Migration completed successfully.";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>