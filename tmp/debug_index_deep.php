<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$date = isset($_GET['date']) ? $_GET['date'] : '2026-03-17';

echo "DEBUG INFO:\n";
echo "Date: $date\n";

$rooms_query = "
    SELECT br.id, br.room_name,
    (SELECT COUNT(*) FROM boarding_room_members WHERE room_id = br.id) as total_students,
    (SELECT COUNT(*) FROM boarding_attendances WHERE room_id = br.id AND date = ?) as total_attendance_count
    FROM boarding_rooms br
";
$stmt = $conn->prepare($rooms_query);
$stmt->execute([$date]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rooms as $room) {
    echo "Room ID: {$room['id']}, Name: {$room['room_name']}, Students: {$room['total_students']}, Attendance: {$room['total_attendance_count']}\n";
}

$all_attendance = $conn->query("SELECT * FROM boarding_attendances WHERE date = '$date'")->fetchAll();
echo "\nAll Attendance for $date: " . count($all_attendance) . " records found.\n";
foreach($all_attendance as $at) {
    echo "ID: {$at['id']}, Room: {$at['room_id']}, Student: {$at['student_id']}, Date: {$at['date']}, Status: {$at['status']}\n";
}
