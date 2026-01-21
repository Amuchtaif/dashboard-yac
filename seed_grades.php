<?php
require_once 'config/app.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$levels = [
    // MTs
    ['name' => 'Class 7', 'category' => 'MTs'],
    ['name' => 'Class 8', 'category' => 'MTs'],
    ['name' => 'Class 9', 'category' => 'MTs'],
    // MA
    ['name' => 'Class 10', 'category' => 'MA'],
    ['name' => 'Class 11', 'category' => 'MA'],
    ['name' => 'Class 12', 'category' => 'MA'],
    // Ma'had Aly
    ['name' => 'Semester 1', 'category' => "Ma'had Aly"],
    ['name' => 'Semester 2', 'category' => "Ma'had Aly"],
];

$stmt = $conn->prepare("INSERT INTO grade_levels (name, category) VALUES (:name, :category)");

$count = 0;
foreach ($levels as $l) {
    // Check if exists
    $check = $conn->prepare("SELECT id FROM grade_levels WHERE name = :name AND category = :category");
    $check->execute([':name' => $l['name'], ':category' => $l['category']]);

    if ($check->rowCount() == 0) {
        $stmt->execute([':name' => $l['name'], ':category' => $l['category']]);
        $count++;
    }
}

echo "Seeded $count new grade levels.";
?>