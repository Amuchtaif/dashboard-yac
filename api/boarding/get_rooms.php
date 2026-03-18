<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $supervisor_id = isset($_GET['supervisor_id']) ? $_GET['supervisor_id'] : null;
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    $query = "
        SELECT br.id, br.room_name, br.supervisor_id, e.full_name as supervisor_name,
        (SELECT COUNT(*) FROM boarding_room_members WHERE room_id = br.id) as total_students,
        (SELECT COUNT(*) FROM boarding_attendances WHERE room_id = br.id AND date = :date) as attended_count,
        (SELECT COUNT(*) FROM boarding_attendances WHERE room_id = br.id AND date = :date) as total_attendance_count,
        (SELECT COUNT(*) FROM boarding_attendances WHERE room_id = br.id AND date = :date) > 0 as is_filled
        FROM boarding_rooms br
        JOIN employees e ON br.supervisor_id = e.id
    ";

    if ($supervisor_id) {
        $query .= " WHERE br.supervisor_id = :supervisor_id ";
    }

    $query .= " ORDER BY br.room_name ASC";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':date', $date);
    if ($supervisor_id) {
        $stmt->bindParam(':supervisor_id', $supervisor_id);
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

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>
