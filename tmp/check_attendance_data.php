<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT * FROM boarding_attendances LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
