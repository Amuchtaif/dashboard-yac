<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_GET['room_id'] = 3;

require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

$room_id = $_GET['room_id'];

$query = "
    SELECT s.id as student_id, s.nama_siswa, s.nomor_induk, s.kelas, s.foto,
           br.room_name as asrama
    FROM boarding_room_members brm
    JOIN students s ON brm.student_id = s.id
    JOIN boarding_rooms br ON brm.room_id = br.id
    WHERE s.status = 'Aktif' AND brm.room_id = :room_id
    ORDER BY s.nama_siswa ASC
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':room_id', $room_id);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Students Count for Room $room_id: " . count($students) . "\n";
print_r($students);
?>
