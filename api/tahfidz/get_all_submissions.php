<?php
// api/tahfidz/get_all_submissions.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
    if (stripos($row['position_name'] ?? '', 'Koordinator Tahfidz') !== false) {
        $isKoordinator = true;
    }
}
if ($user_id == 1) $isKoordinator = true;

if (!$isKoordinator) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access Denied"]);
    exit;
}

try {
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    // Get active academic year
    $activeYearId = 0;
    $yearQuery = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
    $yearResult = $mysqli->query($yearQuery);
    if ($yearResult && $yearResult->num_rows > 0) {
        $yearRow = $yearResult->fetch_assoc();
        $activeYearId = (int)$yearRow['id'];
    }

    // tahfidz_memorization table
    // Assuming structure: id, student_id, teacher_id, date, status, notes
    $query = "SELECT 
                tm.id, tm.student_id, tm.teacher_id, tm.date, tm.surah_start, tm.start_ayah AS ayat_start, tm.surah_end, tm.end_ayah AS ayat_end, tm.juz, tm.status, tm.notes, tm.created_at,
                s.nama_siswa as student_name,
                gl.name as kelas,
                e.full_name as teacher_name
              FROM memorization_entries tm
              JOIN students s ON tm.student_id = s.id
              LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = $activeYearId AND sch.status = 'ACTIVE'
              LEFT JOIN grade_levels gl ON sch.class_id = gl.id
              LEFT JOIN employees e ON tm.teacher_id = e.id
              WHERE tm.date = ?
              ORDER BY tm.created_at DESC";

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
