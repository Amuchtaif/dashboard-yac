<?php
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
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    if (!$room_id || $room_id == 0) {
        echo json_encode(["success" => false, "message" => "ID Asrama tidak valid (room_id is required)"]);
        exit;
    }

    // Fetch Active Academic Year
    $active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    if (!$active_year_id) {
        $active_year_id = 1;
    }

    $query = "
        SELECT s.id as student_id, s.nama_siswa, s.nomor_induk, gl.name as kelas,
               (SELECT status FROM boarding_attendances ba 
                WHERE ba.student_id = s.id AND ba.room_id = brm.room_id AND ba.date = :date 
                LIMIT 1) as status,
               (SELECT notes FROM boarding_attendances ba 
                WHERE ba.student_id = s.id AND ba.room_id = brm.room_id AND ba.date = :date 
                LIMIT 1) as keterangan
        FROM boarding_room_members brm
        JOIN students s ON brm.student_id = s.id
        LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
        LEFT JOIN grade_levels gl ON sch.class_id = gl.id
        WHERE brm.room_id = :room_id AND s.status = 'Aktif'
        ORDER BY s.nama_siswa ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':room_id', $room_id);
    $stmt->bindParam(':active_year_id', $active_year_id);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if room is filled for this date
    $check_filled_stmt = $conn->prepare("
        SELECT ba.created_by, (SELECT full_name FROM employees WHERE id = ba.created_by) as creator_name
        FROM boarding_attendances ba
        WHERE ba.room_id = ? AND ba.date = ? AND ba.created_by IS NOT NULL
        LIMIT 1
    ");
    $check_filled_stmt->execute([$room_id, $date]);
    $filled_res = $check_filled_stmt->fetch(PDO::FETCH_ASSOC);
    
    $is_filled = (bool)$filled_res;
    $filled_by_name = $filled_res['creator_name'] ?? null;
    $filled_by_id = $filled_res['created_by'] ?? null;
    $current_supervisor_id = isset($_GET['supervisor_id']) ? $_GET['supervisor_id'] : null;

    // Locked if filled by someone else
    $is_locked = ($is_filled && $current_supervisor_id && $filled_by_id != $current_supervisor_id);
    // If no supervisor_id passed but it IS filled, we can't be sure, but let's just say it's filled.
    // If current_supervisor_id is NOT passed, we might just warn the user.
    
    echo json_encode([
        "success" => true,
        "count" => count($students),
        "is_filled" => (int)$is_filled,
        "is_locked" => (int)$is_locked,
        "filled_by_name" => $filled_by_name,
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
