<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

$room_id = 3;
$sql = "SELECT s.id, s.nama_siswa, s.status FROM boarding_room_members brm JOIN students s ON brm.student_id = s.id WHERE brm.room_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$room_id]);
$students = $stmt->fetchAll();

echo "Room $room_id students:\n";
print_r($students);
?>
