<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT e.id, e.full_name, p.name FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE p.name IS NOT NULL LIMIT 200");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "ID: " . $row['id'] . " Name: " . $row['full_name'] . " Pos: " . $row['name'] . "\n"; }
?>
