<?php
// api/tahfidz/get_all_attendance.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// Coordinator Check
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
    if (stripos($row['position_name'], 'Koordinator Tahfidz') !== false) {
        $isKoordinator = true;
    }
}
if ($user_id == 1) $isKoordinator = true;

if (!$isKoordinator) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access Denied"]);
    exit;
}

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    // Join path: Attendance -> Student -> Halaqah Member -> Group -> Teacher
    $query = "SELECT 
                ta.*,
                s.nama_siswa as student_name,
                s.kelas,
                hg.group_name,
                e.full_name as teacher_name
              FROM tahfidz_attendance ta
              JOIN students s ON ta.student_id = s.id
              LEFT JOIN halaqah_members hm ON s.id = hm.student_id
              LEFT JOIN halaqah_groups hg ON hm.group_id = hg.id
              LEFT JOIN employees e ON hg.teacher_id = e.id
              WHERE ta.date = ?
              ORDER BY hg.group_name ASC, s.nama_siswa ASC";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode([
        "success" => true,
        "date" => $date,
        "count" => count($data),
        "data" => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
