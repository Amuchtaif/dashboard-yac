<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE tahfidz_assessments");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Field: {$row['Field']}, Type: {$row['Type']}\n";
}
