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

    $supervisor_id = isset($_GET['supervisor_id']) ? $_GET['supervisor_id'] : null;
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    $query = "
        SELECT br.id, br.room_name, br.supervisor_id,
        (SELECT full_name FROM employees WHERE id = br.supervisor_id) as supervisor_name,
        (SELECT GROUP_CONCAT(e.full_name SEPARATOR ', ') FROM boarding_room_supervisors brs JOIN employees e ON brs.supervisor_id = e.id WHERE brs.room_id = br.id) as supervisor_names,
        (SELECT GROUP_CONCAT(brs.supervisor_id SEPARATOR ',') FROM boarding_room_supervisors brs WHERE brs.room_id = br.id) as supervisor_ids,
        (SELECT COUNT(*) FROM boarding_room_members brm2 JOIN students s2 ON brm2.student_id = s2.id WHERE brm2.room_id = br.id AND s2.status = 'Aktif') as total_students,
        (SELECT COUNT(*) FROM boarding_attendances WHERE room_id = br.id AND date = :date1) as attended_count,
        (SELECT COUNT(DISTINCT student_id) FROM boarding_attendances WHERE room_id = br.id AND date = :date2) as total_attendance_count,
        (SELECT COUNT(*) FROM boarding_attendances WHERE room_id = br.id AND date = :date3) > 0 as is_filled,
        (SELECT e.full_name FROM boarding_attendances ba JOIN employees e ON ba.created_by = e.id WHERE ba.room_id = br.id AND ba.date = :date4 LIMIT 1) as filled_by_name
        FROM boarding_rooms br
    ";

    if ($supervisor_id) {
        $query .= " WHERE (EXISTS (SELECT 1 FROM boarding_room_supervisors brs WHERE brs.room_id = br.id AND brs.supervisor_id = :supervisor_id) OR br.supervisor_id = :supervisor_id_legacy) ";
    }

    $query .= " ORDER BY CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(br.room_name, ' (', 1), ' ', -1) AS UNSIGNED) ASC, br.room_name ASC";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date1', $date);
    $stmt->bindParam(':date2', $date);
    $stmt->bindParam(':date3', $date);
    $stmt->bindParam(':date4', $date);
    if ($supervisor_id) {
        $stmt->bindParam(':supervisor_id', $supervisor_id);
        $stmt->bindParam(':supervisor_id_legacy', $supervisor_id);
    }
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast is_filled to boolean for JSON consistency
    foreach ($rooms as &$room) {
        $room['is_filled'] = (bool)$room['is_filled'];
        $room['attended_count'] = (int)$room['attended_count'];
        $room['total_students'] = (int)$room['total_students'];
        $room['total_attendance_count'] = (int)$room['total_attendance_count'];
    }

    echo json_encode([
        "success" => true,
        "count" => count($rooms),
        "data" => $rooms
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>
