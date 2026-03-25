<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT e.id, e.full_name, e.unit_id, u.name as unit_name FROM employees e LEFT JOIN units u ON e.unit_id = u.id WHERE e.id = 82");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "ID: " . $row['id'] . " Name: " . $row['full_name'] . " Unit: " . $row['unit_id'] . " Name: " . $row['unit_name'] . "\n"; }
?>
