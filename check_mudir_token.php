<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT fcm_token FROM employees WHERE id = 199");
echo "Token 199: " . $stmt->fetchColumn() . "\n";
?>
