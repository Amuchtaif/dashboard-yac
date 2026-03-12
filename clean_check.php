<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

function get_describe($conn, $table) {
    echo "--- TABLE: $table ---\n";
    $stmt = $conn->query("DESCRIBE $table");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "{$row['Field']} - {$row['Type']} - {$row['Key']} - {$row['Default']}\n";
    }
    echo "\n";
}

get_describe($conn, 'assessment_types');
get_describe($conn, 'tahfidz_assessments');

echo "--- assessment_types data ---\n";
$stmt = $conn->query("SELECT * FROM assessment_types");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    print_r($row);
}
