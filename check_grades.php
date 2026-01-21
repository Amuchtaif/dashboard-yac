<?php
require_once 'config/app.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$levels = $conn->query("SELECT * FROM grade_levels")->fetchAll(PDO::FETCH_ASSOC);

echo "Total Levels: " . count($levels) . "\n";
foreach ($levels as $l) {
    printf("ID: %d | Name: %-20s | Category: '%s'\n", $l['id'], $l['name'], $l['category']);
}
?>