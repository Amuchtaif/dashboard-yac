<?php
// api/tahfidz/verify_teacher_attendance.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");


include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Database configuration file not found or connection variable not set."]));
}


$input = json_decode(file_get_contents("php://input"), true);
$id = isset($input['id']) ? $input['id'] : null; // Attendance ID
$action = isset($input['action']) ? $input['action'] : null; // 'approve' or 'reject'

if (!$id || !$action) {
    echo json_encode(["success" => false, "message" => "ID and action are required"]);
    exit;
}

try {
    if ($action === 'approve') {
        // Mark as verified, set status_approval to approved
        $stmt = $mysqli->prepare("UPDATE tahfidz_teacher_attendance SET is_verified = 1, status_approval = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $id);
    } elseif ($action === 'reject') {
        // Mark as verified, set status_approval to rejected, and status to Alpha
        $stmt = $mysqli->prepare("UPDATE tahfidz_teacher_attendance SET is_verified = 1, status_approval = 'rejected', status = 'Alpha' WHERE id = ?");
        $stmt->bind_param("i", $id);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action"]);
        exit;
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Verification successful"]);
    } else {
        echo json_encode(["success" => false, "message" => "Database update failed"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
