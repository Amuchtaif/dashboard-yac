<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $conn->exec("ALTER TABLE attendances ADD COLUMN note TEXT NULL AFTER status_out");
} catch (Exception $e) {}

try {
    $conn->exec("ALTER TABLE attendances ADD COLUMN created_by INT NULL AFTER note");
} catch (Exception $e) {}

echo "Done";
?>
