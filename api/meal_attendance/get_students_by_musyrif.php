<?php
// api/meal_attendance/get_students_by_musyrif.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $musyrif_id = $_GET['musyrif_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    $meal_type = $_GET['meal_type'] ?? 'Siang';

    if (!$musyrif_id) {
        echo json_encode(["success" => false, "message" => "musyrif_id parameter is required."]);
        exit();
    }

    // 1. Detect which room is supervised by this musyrif_id
    $room_stmt = $conn->prepare("SELECT id, room_name FROM boarding_rooms WHERE supervisor_id = ? LIMIT 1");
    $room_stmt->execute([$musyrif_id]);
    $room = $room_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        echo json_encode(["success" => false, "message" => "Musyrif belum ditetapkan ke asrama manapun."]);
        exit();
    }

    // 2. Get students in this room and their meal attendance status
    $sql = "
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk, 
            s.kelas,
            ma.id as attendance_id,
            ma.check_time
        FROM students s
        JOIN boarding_room_members brm ON s.id = brm.student_id
        LEFT JOIN meal_attendances ma ON s.id = ma.student_id 
            AND ma.meal_type = :meal_type 
            AND ma.date = :date
        WHERE brm.room_id = :room_id
        ORDER BY s.nama_siswa ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':room_id' => $room['id'],
        ':meal_type' => $meal_type,
        ':date' => $date
    ]);
    
    $students_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $students = [];

    foreach ($students_raw as $row) {
        $students[] = [
            "id" => $row['id'],
            "nama_siswa" => $row['nama_siswa'],
            "nomor_induk" => $row['nomor_induk'],
            "kelas" => $row['kelas'],
            "room_name" => $room['room_name'],
            "attendance_id" => $row['attendance_id'],
            "check_time" => $row['check_time'],
            "status" => ($row['attendance_id'] ? "Sudah" : "Belum")
        ];
    }

    echo json_encode([
        "success" => true, 
        "room_info" => $room,
        "date" => $date,
        "meal_type" => $meal_type,
        "data" => $students
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
