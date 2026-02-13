<?php
// api/tahfidz/submit_tahfidz_record.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/permission.php';

$input = json_decode(file_get_contents("php://input"), true);

// Extract data
$student_id = isset($input['student_id']) ? $input['student_id'] : null;
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;
$date = isset($input['date']) ? $input['date'] : date('Y-m-d');
$surah_start = isset($input['surah_start']) ? $input['surah_start'] : null;
$ayat_start = isset($input['ayat_start']) ? $input['ayat_start'] : 0;
$surah_end = isset($input['surah_end']) ? $input['surah_end'] : null;
$ayat_end = isset($input['ayat_end']) ? $input['ayat_end'] : 0;
$juz = isset($input['juz']) ? $input['juz'] : null;
$status = isset($input['status']) ? $input['status'] : 'Lancar';
$notes = isset($input['notes']) ? $input['notes'] : '';

// Validation
if (!$student_id || !$teacher_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Student ID and Teacher ID are required"]);
    exit;
}

// Verify teacher permission
if (!hasPermission($teacher_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki akses Tahfidz."]);
    exit;
}

// Optional: Verify if student is actually in teacher's halaqah
// The prompt says "Pastikan API ini memverifikasi bahwa teacher_id memang memiliki akses sebelum memproses data."
// This could mean general access or specific access to that student.
// General access is checked by hasPermission.
// Let's add specific verification for robustness.
try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmtCheck = $conn->prepare("SELECT 1 FROM halaqah_members hm 
                               JOIN halaqah_groups hg ON hm.group_id = hg.id 
                               WHERE hm.student_id = ? AND hg.teacher_id = ? LIMIT 1");
    $stmtCheck->execute([$student_id, $teacher_id]);
    if (!$stmtCheck->fetch()) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Forbidden: Santri ini bukan bagian dari Halaqah Anda."]);
        exit;
    }

    // Insert into tahfidz_memorization
    $query = "INSERT INTO tahfidz_memorization 
              (student_id, date, surah_start, ayat_start, surah_end, ayat_end, juz, status, notes, teacher_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $result = $stmt->execute([
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
    ]);

    if ($result) {
        echo json_encode([
            "success" => true, 
            "message" => "Setoran hafalan berhasil disimpan",
            "id" => $conn->lastInsertId()
        ]);
    } else {
        throw new Exception("Failed to execute insert statement");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
