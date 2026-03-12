<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- UNITS ---\n";
$stmt = $conn->query("SELECT * FROM units LIMIT 20");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['id'] . ": " . $row['name'] . "\n";
}

echo "\n--- EDUCATION_UNITS ---\n";
$stmt = $conn->query("SELECT * FROM education_units LIMIT 20");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['id'] . ": " . $row['name'] . "\n";
}
