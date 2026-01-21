<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Specific Class Debug ---\n";
$name = "%Semester 2 Akhwat%";
$stmt = $conn->prepare("SELECT id, name, education_unit_id FROM grade_levels WHERE name LIKE :name");
$stmt->execute([':name' => $name]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($grades) {
    foreach ($grades as $g) {
        echo sprintf("Grade: %s (ID: %s), Unit ID: %s\n", $g['name'], $g['id'], $g['education_unit_id'] ?? 'NULL');
    }
} else {
    echo "Class not found.\n";
}

echo "\n--- All Education Units ---\n";
$stmt = $conn->query("SELECT id, name FROM education_units");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($units as $u) {
    echo sprintf("[%s] %s\n", $u['id'], $u['name']);
}
?>