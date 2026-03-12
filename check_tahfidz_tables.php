<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- TABLES ---\n";
$stmt = $conn->query("SHOW TABLES LIKE 'tahfidz%'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

echo "\n--- TABLE: assessment_types (if any) ---\n";
$stmt = $conn->query("SHOW TABLES LIKE '%assessment_types%'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
