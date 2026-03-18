<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$room_id = 1;
$date = '2026-03-17';
$query = "
    SELECT s.id as student_id, s.nama_siswa, ba.status, ba.room_id as ba_room, brm.room_id as brm_room
    FROM boarding_room_members brm
    JOIN students s ON brm.student_id = s.id
    LEFT JOIN boarding_attendances ba ON ba.student_id = s.id AND ba.room_id = brm.room_id AND ba.date = :date
    WHERE brm.room_id = :room_id
";
$stmt = $conn->prepare($query);
$stmt->execute(['date' => $date, 'room_id' => $room_id]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
