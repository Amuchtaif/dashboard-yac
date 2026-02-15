<?php
// api/tahfidz/approve_attendance.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

// Session Logic
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// Check if user is Koordinator Tahfidz
$isKoordinator = false;
$stmt = $mysqli->prepare("SELECT p.position_name FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    if (stripos($row['position_name'], 'Koordinator Tahfidz') !== false) {
        $isKoordinator = true;
    }
}
if ($user_id == 1) $isKoordinator = true; // Admin Override

if (!$isKoordinator) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access Denied: Only Koordinator Tahfidz can approve attendance."]);
    exit;
}

// Input
$input = json_decode(file_get_contents("php://input"), true);
$attendance_id = isset($input['attendance_id']) ? intval($input['attendance_id']) : 0;

if ($attendance_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid Attendance ID"]);
    exit;
}

try {
    // Update
    $query = "UPDATE tahfidz_teacher_attendance 
              SET status_approval = 'approved', 
                  approved_by = ?, 
                  approval_time = NOW() 
              WHERE id = ?";

    $stmt = $mysqli->prepare($query);
    file_put_contents('../../debug_api.txt', "Approving ID: $attendance_id | Session UID: " . var_export($_SESSION['user_id'], true) . " | UserID Var: " . var_export($user_id, true) . "\n", FILE_APPEND);
    
    $stmt->bind_param("ii", $user_id, $attendance_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Approved successfully"]);
        } else {
            // Check if already approved or ID not found
            // Technically successful if no change but already approved, but usually we prefer feedback.
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
