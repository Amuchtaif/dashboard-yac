<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();
echo "Searching...\n";
$stmt = $conn->query("SELECT id, name, unit_id, division_id, position_id FROM employees WHERE name LIKE '%Idris%' OR name LIKE '%Ma%' LIMIT 20");
if ($stmt) {
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    echo "Query failed.\n";
}
?>