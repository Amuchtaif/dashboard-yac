<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- DIVISIONS ---\n";
$stmt = $conn->query("SELECT * FROM divisions");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['id'] . ": " . $row['name'] . "\n";
}

echo "\n--- UNITS ---\n";
$stmt = $conn->query("SELECT * FROM units LIMIT 50");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['id'] . ": " . $row['name'] . " (Division: " . ($row['division_id'] ?? 'null') . ")\n";
}
