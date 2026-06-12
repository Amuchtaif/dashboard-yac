<?php
// api/tahfidz/verify_teacher_attendance.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Database configuration file not found or connection variable not set."]));
}


$input = json_decode(file_get_contents("php://input"), true) ?? [];
file_put_contents(__DIR__ . '/../../debug_verify_raw.txt', date('H:i:s') . " | Input: " . json_encode($input) . " | Session UID: " . var_export($_SESSION['user_id'] ?? 'NONE', true) . "\n", FILE_APPEND);
$id = isset($input['id']) ? $input['id'] : null; // Attendance ID
$action = isset($input['action']) ? $input['action'] : null; // 'approve' or 'reject'

if (!$id || !$action) {
    echo json_encode(["success" => false, "message" => "ID and action are required"]);
    exit;
}

try {
    // Determine who is performing the verification
    $approver_id = $_SESSION['user_id'] ?? ($input['approver_id'] ?? null);
    
    // Fallback logic: if it's the mobile app and we don't have a session, 
    // we might need a default or require it. 
    // In this system, Ahmad Ghozali is ID 25.
    
    if ($action === 'approve') {
        // Mark as verified, set status_approval to approved
        $stmt = $mysqli->prepare("UPDATE tahfidz_teacher_attendance SET is_verified = 1, status_approval = 'approved', approved_by = ?, approval_time = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $approver_id, $id);
    } elseif ($action === 'reject') {
        // Mark as verified, set status_approval to rejected, and status to Alpha
        $stmt = $mysqli->prepare("UPDATE tahfidz_teacher_attendance SET is_verified = 1, status_approval = 'rejected', status = 'Alpha', approved_by = ?, approval_time = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $approver_id, $id);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action"]);
        exit;
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Verification successful", "approver_id" => $approver_id]);
    } else {
        echo json_encode(["success" => false, "message" => "Database update failed: " . $stmt->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
