<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SELECT * FROM employees LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

print_r($user);
?>