<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $conn->exec("ALTER TABLE ramadan_overrides ADD COLUMN label VARCHAR(255) AFTER id");
    echo "Column label added.\n";
} catch (Exception $e) { echo "Column might exist.\n"; }
?>
