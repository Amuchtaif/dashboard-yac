<?php
// api/tahfidz/get_pending_approvals.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

// Session Logic: APIs might not carry session if called from outside browser context, 
// but typically for AJAX, cookies are sent.
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

$pos_name_col = 'name';
$checkCol = $mysqli->query("SHOW COLUMNS FROM positions LIKE 'position_name'");
if ($checkCol && $checkCol->num_rows > 0) {
    $pos_name_col = 'position_name';
}

// Direct Query for Position Name
$stmt = $mysqli->prepare("SELECT p.{$pos_name_col} AS position_name FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    if (stripos($row['position_name'], 'Koordinator Tahfidz') !== false) {
        $isKoordinator = true;
    }
}

// Allow Admin (ID 1) as workaround
if ($user_id == 1) $isKoordinator = true;

if (!$isKoordinator) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access Denied: Only Koordinator Tahfidz can view approvals."]);
    exit;
}

try {
    // Determine the query
    // Show PENDING approvals
    // Logic: status_approval = 'pending' AND status = 'Hadir' (or whatever status needs approval)
    // Actually, any 'pending' needs approval.
    
    $query = "SELECT 
                ta.id, 
                ta.date, 
                ta.check_in_time, 
                ta.check_out_time, 
                ta.notes, 
                ta.status,
                e.full_name as teacher_name
              FROM tahfidz_teacher_attendance ta
              JOIN employees e ON ta.teacher_id = e.id
              WHERE ta.status_approval = 'pending'
              ORDER BY ta.date DESC, ta.check_in_time ASC";

    $result = $mysqli->query($query);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($data),
        "data" => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
