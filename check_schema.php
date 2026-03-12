<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- UNITS ---\n";
$stmt = $conn->query("DESCRIBE units");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- EDUCATION_UNITS ---\n";
$stmt = $conn->query("DESCRIBE education_units");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- DIVISIONS ---\n";
$stmt = $conn->query("DESCRIBE divisions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
