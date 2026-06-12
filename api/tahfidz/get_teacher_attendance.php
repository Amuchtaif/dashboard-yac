<?php
// api/tahfidz/get_teacher_attendance.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

$date = isset($_GET['date']) ? $_GET['date'] : null;
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;

try {
    $attendance_records = [];
    $query = "SELECT t.*, t.is_verified, t.status_approval, e.full_name as teacher_name, '' as teacher_photo, u.name as unit_name
              FROM tahfidz_teacher_attendance t
              LEFT JOIN employees e ON t.teacher_id = e.id
              LEFT JOIN units u ON e.unit_id = u.id
              WHERE 1=1";

    $params = [];
    $types = "";

    if ($date) {
        $query .= " AND t.date = ?";
        $params[] = $date;
        $types .= "s";
    }

    if ($teacher_id) {
        $query .= " AND t.teacher_id = ?";
        $params[] = $teacher_id;
        $types .= "i";
    }
    
    $query .= " ORDER BY t.date DESC";

    $stmt = $mysqli->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($attendance_records),
        "data" => $attendance_records
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
