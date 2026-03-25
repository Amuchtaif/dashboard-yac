<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW COLUMNS FROM employees");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo $row['Field'] . ", "; }
?>
