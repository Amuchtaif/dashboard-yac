<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT * FROM employees WHERE id = 82");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { var_dump($row); }
?>
