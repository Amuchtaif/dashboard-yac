<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE tahfidz_assessments");
echo "| Field | Type | Null | Key | Default | Extra |\n";
echo "|---|---|---|---|---|---|\n";
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "| {$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']} |\n";
}
