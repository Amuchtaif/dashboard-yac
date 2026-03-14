<?php
require_once 'config/app.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "--- Ramadan Settings ---\n";
$stmt = $conn->query("SELECT * FROM ramadan_settings");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Units Table Columns ---\n";
$stmt = $conn->query("SHOW COLUMNS FROM units");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Affected Units ---\n";
$stmt = $conn->query("SELECT id, name, is_ramadan_affected FROM units WHERE is_ramadan_affected = 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
