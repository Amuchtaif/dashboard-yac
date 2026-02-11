<?php
// api/tahfidz/submit_memorization.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/db_mysqli.php';

$input = json_decode(file_get_contents("php://input"), true);

$student_id = isset($input['student_id']) ? $input['student_id'] : null;
$date = isset($input['date']) ? $input['date'] : date('Y-m-d');
$surah_start = isset($input['surah_start']) ? $input['surah_start'] : '';
$ayat_start = isset($input['ayat_start']) ? $input['ayat_start'] : 0;
$surah_end = isset($input['surah_end']) ? $input['surah_end'] : '';
$ayat_end = isset($input['ayat_end']) ? $input['ayat_end'] : 0;
$juz = isset($input['juz']) ? $input['juz'] : null;
$status = isset($input['status']) ? $input['status'] : 'Lancar'; // Lancar, Kurang Lancar, Ulang, etc.
$notes = isset($input['notes']) ? $input['notes'] : '';
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;

if (!$student_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Student ID is required"]);
    exit;
}

// Check Permission
include_once '../../config/permission.php';
if ($teacher_id && !hasPermission($teacher_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki akses Tahfidz."]);
    exit;
}

try {
    $stmt = $mysqli->prepare("INSERT INTO tahfidz_memorization 
        (student_id, date, surah_start, ayat_start, surah_end, ayat_end, juz, status, notes, teacher_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("issississi", 
        $student_id, 
        $date, 
        $surah_start, 
        $ayat_start, 
        $surah_end, 
        $ayat_end, 
        $juz, 
        $status, 
        $notes, 
        $teacher_id
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Memorization record saved successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to save record: " . $stmt->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
