<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(", ", $tables) . "\n";

$meeting_id = 1; // Try to find one
$stmt = $conn->query("SELECT id FROM meetings LIMIT 1");
$m = $stmt->fetch();
if ($m) {
    $meeting_id = $m['id'];
    echo "Found meeting ID: $meeting_id\n";
} else {
    echo "No meetings found\n";
}

try {
    $sql = "SELECT m.*, d.name as division_name, e.full_name as creator_name 
            FROM meetings m 
            LEFT JOIN divisions d ON m.division_id = d.id 
            LEFT JOIN employees e ON m.created_by = e.id 
            WHERE m.id = $meeting_id";
    $conn->query($sql);
    echo "Query 1 OK\n";
} catch (Exception $e) {
    echo "Query 1 Error: " . $e->getMessage() . "\n";
}
?>
