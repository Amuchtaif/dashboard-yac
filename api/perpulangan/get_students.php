<?php
// api/perpulangan/get_students.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $room_id = isset($_GET['room_id']) ? $_GET['room_id'] : null;

    if (!$room_id) {
        echo json_encode(["status" => false, "message" => "room_id is required"]);
        exit;
    }

    // Build query to get students only from the specific room_id, same as boarding
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

    echo json_encode([
        "success" => true,
        "count" => count($students),
        "data" => $students
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>
