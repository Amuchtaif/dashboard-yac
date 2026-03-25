<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT * FROM boarding_permits WHERE id = 10 OR id = 11 OR id = 12 OR id = 13");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { var_dump($row); }
?>
