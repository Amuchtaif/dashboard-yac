<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Debugging Unit Data ---\n";

// Check Grade Levels and their Unit IDs
echo "\n--- Grade Levels (Sample) ---\n";
$stmt = $conn->query("SELECT id, name, education_unit_id FROM grade_levels LIMIT 10");
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($grades as $g) {
    echo sprintf("ID: %s, Name: %s, Unit ID: %s\n", $g['id'], $g['name'], $g['education_unit_id'] ?? 'NULL');
}

// Check Education Units
echo "\n--- Education Units ---\n";
$stmt = $conn->query("SELECT id, name FROM education_units");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($units as $u) {
    echo sprintf("ID: %s, Name: %s\n", $u['id'], $u['name']);
}
?>