<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT room_name FROM boarding_rooms");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
