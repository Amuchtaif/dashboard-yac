<?php
// api/tahfidz/get_students.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit;
}

try {
    $students = [];
    
    // 1. Get active academic year
    $activeYear = "";
    $activeYearId = 0;
    $yearQuery = "SELECT id, name FROM academic_years WHERE is_active = 1 LIMIT 1";
    $yearResult = $mysqli->query($yearQuery);
    
    if ($yearResult && $yearResult->num_rows > 0) {
        $yearRow = $yearResult->fetch_assoc();
        $activeYearId = (int)$yearRow['id'];
        $activeYear = $yearRow['name'];
    }

    // 2. Fetch all students (filtered by status Aktif and excluding specific units)
    $exclude = ["'TKIT'", "'SDIT'", "'PLAY GROUP'"];
    $exclude_str = implode(',', $exclude);
    $query = "SELECT s.*, COALESCE(gl.name, s.kelas, '-') as kelas 
              FROM students s 
              LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = $activeYearId
              LEFT JOIN grade_levels gl ON sch.class_id = gl.id
              WHERE s.status = 'Aktif' 
              AND (s.tingkat NOT IN ($exclude_str) OR s.tingkat IS NULL)
              ORDER BY s.nama_siswa ASC";
    $result = $mysqli->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } else {
        throw new Exception("Error executing query: " . $mysqli->error);
    }

    echo json_encode([
        "success" => true,
        "count" => count($students),
        "data" => $students,
        "active_year_debug" => $activeYear 
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
