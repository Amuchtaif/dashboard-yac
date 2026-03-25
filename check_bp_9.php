<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT * FROM boarding_permits WHERE id = 9");
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC)) . "\n";
?>
