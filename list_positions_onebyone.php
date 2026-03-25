<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, name FROM positions");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo $row['id'] . ":" . $row['name'] . "\n"; }
?>
