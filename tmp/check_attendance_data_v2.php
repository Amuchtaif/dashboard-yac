<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, room_id, student_id, date, status FROM boarding_attendances ORDER BY id DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "\nToday is: " . date('Y-m-d') . "\n";
