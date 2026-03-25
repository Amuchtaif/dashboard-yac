<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT e.id, e.full_name, p.name FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = 179");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "ID: " . $row['id'] . " Name: " . $row['full_name'] . " Pos: " . $row['name'] . "\n"; }
?>
