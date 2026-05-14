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
$total_baris = isset($input['total_baris']) ? $input['total_baris'] : 0;
$juz = isset($input['juz']) ? $input['juz'] : null;
$status = isset($input['status']) ? $input['status'] : 'Lancar'; // Lancar, Kurang Lancar, Ulang, etc.
$notes = isset($input['notes']) ? $input['notes'] : '';
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;

// Auto-detect Juz from Quran API if not provided or to ensure accuracy
if ($surah_start && $ayat_start) {
    // Only attempt if surah_start is numeric (Surah ID)
    if (is_numeric($surah_start)) {
        $url = "https://api.alquran.cloud/v1/ayah/{$surah_start}:{$ayat_start}";
        $api_res = @file_get_contents($url);
        if ($api_res) {
            $api_data = json_decode($api_res, true);
            if (isset($api_data['data']['juz'])) {
                $juz = $api_data['data']['juz'];
            }
        }
    }
}

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
        (student_id, date, surah_start, ayat_start, total_baris, surah_end, ayat_end, juz, status, notes, teacher_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Default surah_end and ayat_end if not provided
    $surah_end = isset($input['surah_end']) ? $input['surah_end'] : $surah_start;
    $ayat_end = isset($input['ayat_end']) ? $input['ayat_end'] : $ayat_start;

    $stmt->bind_param("issiiisissi", 
        $student_id, 
        $date, 
        $surah_start, 
        $ayat_start, 
        $total_baris,
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
