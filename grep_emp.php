<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, full_name, email FROM employees WHERE full_name LIKE '%Kep%' OR email LIKE '%kep%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "ID: " . $row['id'] . " Name: " . $row['full_name'] . " Email: " . $row['email'] . "\n"; }
?>
