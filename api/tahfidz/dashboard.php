<?php
// api/tahfidz/dashboard.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Tahfidz/ProgressService.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if ($student_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Student ID is required."]);
    exit;
}

$progressService = new ProgressService();
$ay = $progressService->getActiveAcademicYear();

if (!$ay) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Active academic year not found."]);
    exit;
}

try {
    // Get progress calculations
    $prog = $progressService->getStudentProgress($student_id, $ay['id']);

    // Get entry counts per type
    require_once __DIR__ . '/../../config/db_mysqli.php';
    $stmt = $mysqli->prepare("SELECT 
        SUM(CASE WHEN entry_type = 'HAFALAN_BARU' THEN 1 ELSE 0 END) as total_hafalan_baru,
        SUM(CASE WHEN entry_type = 'MUROJAAH' THEN 1 ELSE 0 END) as total_murojaah,
        SUM(CASE WHEN entry_type = 'TASMI' THEN 1 ELSE 0 END) as total_tasmi,
        SUM(CASE WHEN entry_type = 'UJIAN' THEN 1 ELSE 0 END) as total_ujian
        FROM memorization_entries 
        WHERE student_id = ? AND date BETWEEN ? AND ?");
    
    $stmt->bind_param("iss", $student_id, $ay['start_date'], $ay['end_date']);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        "success" => true,
        "data" => [
            "academic_year" => $ay['name'],
            "semester" => $ay['semester'],
            "total_hafalan_baru" => $counts['total_hafalan_baru'] ? (int)$counts['total_hafalan_baru'] : 0,
            "total_murojaah" => $counts['total_murojaah'] ? (int)$counts['total_murojaah'] : 0,
            "total_tasmi" => $counts['total_tasmi'] ? (int)$counts['total_tasmi'] : 0,
            "total_ujian" => $counts['total_ujian'] ? (int)$counts['total_ujian'] : 0,
            "target_semester" => $prog['target_juz'],
            "progress_semester" => $prog['progress_percentage'],
            "baseline_juz" => $prog['baseline_juz'],
            "total_juz" => $prog['total_juz']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
