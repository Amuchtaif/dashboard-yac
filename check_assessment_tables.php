<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- assessment_types ---\n";
$stmt = $conn->query("DESCRIBE assessment_types");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- tahfidz_assessments ---\n";
$stmt = $conn->query("DESCRIBE tahfidz_assessments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
