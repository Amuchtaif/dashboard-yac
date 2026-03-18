<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$date = '2026-03-17';
$rooms_query = "
    SELECT br.id, br.room_name,
    (SELECT COUNT(*) FROM boarding_room_members WHERE room_id = br.id) as total_students,
    (SELECT COUNT(*) FROM boarding_attendances WHERE room_id = br.id AND date = ?) as total_attendance_count
    FROM boarding_rooms br
";
$stmt = $conn->prepare($rooms_query);
$stmt->execute([$date]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $conn->query("SELECT COUNT(*) FROM boarding_attendances WHERE room_id = 1 AND date = '$date'");
echo "\nCount for room 1: " . $stmt2->fetchColumn() . "\n";
