<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_GET['room_id'] = 3;
$_GET['date'] = date('Y-m-d');

require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

$room_id = $_GET['room_id'];
$date = $_GET['date'];

$query = "
    SELECT s.id as student_id, s.nama_siswa, s.nomor_induk, s.kelas,
           ba.status, ba.notes as keterangan
    FROM boarding_room_members brm
    JOIN students s ON brm.student_id = s.id
    LEFT JOIN boarding_attendances ba ON ba.student_id = s.id AND ba.room_id = brm.room_id AND ba.date = :date
    WHERE brm.room_id = :room_id
    ORDER BY s.nama_siswa ASC
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':date', $date);
$stmt->bindParam(':room_id', $room_id);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Students Count: " . count($students) . "\n";
print_r($students);

// Check if room is filled for this date
$check_filled_stmt = $conn->prepare("
    SELECT ba.created_by, (SELECT full_name FROM employees WHERE id = ba.created_by) as creator_name
    FROM boarding_attendances ba
    WHERE ba.room_id = ? AND ba.date = ? AND ba.created_by IS NOT NULL
    LIMIT 1
");
$check_filled_stmt->execute([$room_id, $date]);
$filled_res = $check_filled_stmt->fetch(PDO::FETCH_ASSOC);

echo "\nFilled Res: ";
print_r($filled_res);
?>
