<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, full_name FROM employees WHERE full_name LIKE '%Lutfi%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "Lutfi -> ID: " . $row['id'] . " Name: " . $row['full_name'] . "\n"; }
$stmt = $conn->query("SELECT id, full_name FROM employees WHERE full_name LIKE '%Amr%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "Amr -> ID: " . $row['id'] . " Name: " . $row['full_name'] . "\n"; }
?>
