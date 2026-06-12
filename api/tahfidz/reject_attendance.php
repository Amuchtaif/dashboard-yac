<?php
// api/tahfidz/reject_attendance.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db_mysqli.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// Check Koordinator
$isKoordinator = false;

$pos_name_col = 'name';
$checkCol = $mysqli->query("SHOW COLUMNS FROM positions LIKE 'position_name'");
if ($checkCol && $checkCol->num_rows > 0) {
    $pos_name_col = 'position_name';
}

$stmt = $mysqli->prepare("SELECT p.{$pos_name_col} AS position_name FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    if (stripos($row['position_name'] ?? '', 'Koordinator Tahfidz') !== false) {
        $isKoordinator = true;
    }
}
if ($user_id == 1) $isKoordinator = true;

if (!$isKoordinator) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access Denied: Only Koordinator Tahfidz can reject attendance."]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true) ?? [];
$attendance_id = isset($input['attendance_id']) ? intval($input['attendance_id']) : 0;
$reason = isset($input['reason']) ? trim((string)$input['reason']) : null;

if ($attendance_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid Attendance ID"]);
    exit;
}

try {
    $query = "UPDATE tahfidz_teacher_attendance 
              SET status_approval = 'rejected', 
                  rejection_reason = ?, 
                  approved_by = ?, 
                  approval_time = NOW() 
              WHERE id = ?";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sii", $reason, $user_id, $attendance_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Attendance rejected successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Attendance not found or already processed"]);
        }
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
