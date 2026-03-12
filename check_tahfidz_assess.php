<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- TABLE: tahfidz_assessments ---\n";
$stmt = $conn->query("DESCRIBE tahfidz_assessments");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
