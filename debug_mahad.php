<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Searching for Units like 'Ma''had' ---\n";
$stmt = $conn->query("SELECT * FROM education_units WHERE name LIKE '%Ma%'");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($units);

echo "\n--- Searching for Classes like 'Mustawa' or 'Ma''had' ---\n";
$stmt = $conn->query("SELECT id, name, education_unit_id FROM grade_levels WHERE name LIKE '%Mustawa%' OR name LIKE '%Ma%'");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($classes);
?>