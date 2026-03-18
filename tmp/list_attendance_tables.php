<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW TABLES LIKE 'boarding_attendance%'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
