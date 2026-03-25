<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW TABLES LIKE 'users'");
echo $stmt->fetch() ? "Users table exists\n" : "No users table\n";
?>
