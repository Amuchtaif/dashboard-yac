<?php
// api/meal_attendance/get_students_list.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $meal_type = $_GET['meal_type'] ?? 'Siang';
    $date = $_GET['date'] ?? date('Y-m-d');
    $grade_id = $_GET['grade_id'] ?? null;
    $room_id = $_GET['room_id'] ?? null;

    $sql = "
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk, 
            s.kelas,
            s.tingkat,
            gl.name as grade_name,
            br.room_name,
            ma.id as attendance_id,
            ma.check_time
        FROM students s
        LEFT JOIN grade_levels gl ON s.kelas = gl.name
        LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
        LEFT JOIN boarding_rooms br ON brm.room_id = br.id
        LEFT JOIN meal_attendances ma ON s.id = ma.student_id 
            AND ma.meal_type = :meal_type 
            AND ma.date = :date
        WHERE 1=1
    ";

    // --- MEAL DISTRIBUTION RULES ---
    // Pagi & Malam: Only for Boarding Students (those who have a room assigned)
    // Siang: For ALL Students (Boarding and Day Students like SDIT)
    if ($meal_type === 'Pagi' || $meal_type === 'Malam') {
        $sql .= " AND brm.room_id IS NOT NULL ";
    }

    $params = [
        ':meal_type' => $meal_type,
        ':date' => $date
    ];

    if ($grade_id) {
        $sql .= " AND (gl.id = :grade_id OR s.kelas = (SELECT name FROM grade_levels WHERE id = :grade_id))";
        $params[':grade_id'] = $grade_id;
    }

    if ($room_id) {
        $sql .= " AND brm.room_id = :room_id";
        $params[':room_id'] = $room_id;
    }

    $sql .= " ORDER BY s.nama_siswa ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
