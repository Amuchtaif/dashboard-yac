<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $conn->exec("ALTER TABLE boarding_permits ADD COLUMN musrif_id INT NULL AFTER student_id");
    echo "Column 'musrif_id' added successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
