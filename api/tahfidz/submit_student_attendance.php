<?php
// api/tahfidz/submit_student_attendance.php

date_default_timezone_set('Asia/Jakarta');

if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit;
}

// Get JSON input
$raw_input = file_get_contents("php://input");
if (empty($raw_input) && php_sapi_name() === 'cli') {
    $raw_input = file_get_contents("php://stdin");
}
$input = json_decode($raw_input, true) ?? [];

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

$date = isset($input['date']) ? substr($input['date'], 0, 10) : date('Y-m-d');
$session = isset($input['session']) && !empty($input['session']) ? $input['session'] : ((int)date('H') >= 12 ? 'Sore' : 'Pagi');
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;
$students = isset($input['students']) ? $input['students'] : [];

// Check Permission
require_once __DIR__ . '/../../config/permission.php';
if ($teacher_id && !hasPermission($teacher_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki akses Tahfidz."]);
    exit;
}

if (empty($students)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "No student data provided"]);
    exit;
}

$success_count = 0;
$errors = [];

try {
    $mysqli->begin_transaction();

    // Persiapkan statement untuk pengecekan, insert, dan update secara terpisah berdasarkan student, date, DAN session
    $check_sql = "SELECT id FROM tahfidz_attendance WHERE student_id = ? AND date = ? AND session = ? LIMIT 1";
    $check_stmt = $mysqli->prepare($check_sql);
    
    $insert_sql = "INSERT INTO tahfidz_attendance (student_id, date, status, session, teacher_id) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $mysqli->prepare($insert_sql);
    
    $update_sql = "UPDATE tahfidz_attendance SET status = ?, session = ?, teacher_id = ? WHERE id = ?";
    $update_stmt = $mysqli->prepare($update_sql);

    if (!$check_stmt || !$insert_stmt || !$update_stmt) {
        throw new Exception("Gagal mempersiapkan query (prepare failed): " . $mysqli->error);
    }

    foreach ($students as $student) {
        if (!isset($student['student_id']) || !isset($student['status'])) {
            $errors[] = "Missing id or status for one record";
            continue; // Lewati yang tidak valid
        }

        $s_id = $student['student_id'];
        $status = $student['status'];

        try {
            // Cek apakah data absensi sudah ada untuk tanggal DAN sesi tersebut
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80100) {
                $check_stmt->execute([$s_id, $date, $session]);
            } else {
                $check_stmt->bind_param("iss", $s_id, $date, $session);
                $check_stmt->execute();
            }
            $check_result = $check_stmt->get_result();
            
            if ($check_row = $check_result->fetch_assoc()) {
                // Jika sudah ada, lakukan UPDATE
                $attendance_id = $check_row['id'];
                if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80100) {
                    $update_stmt->execute([$status, $session, $teacher_id, $attendance_id]);
                } else {
                    $update_stmt->bind_param("ssii", $status, $session, $teacher_id, $attendance_id);
                    $update_stmt->execute();
                }
                $success_count++;
            } else {
                // Jika belum ada, lakukan INSERT
                if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80100) {
                    $insert_stmt->execute([$s_id, $date, $status, $session, $teacher_id]);
                } else {
                    $insert_stmt->bind_param("isssi", $s_id, $date, $status, $session, $teacher_id);
                    $insert_stmt->execute();
                }
                $success_count++;
            }
        } catch (Exception $e) {
            // Sejak PHP 8.1+, MySQLi melempar exception secara default. 
            // Kita tangkap secara lokal di dalam loop agar santri lainnya tetap dapat diproses.
            $errors[] = "Gagal memproses santri ID $s_id: " . $e->getMessage();
        }
    }

    $mysqli->commit();

    echo json_encode([
        "success" => true,
        "message" => "Attendance processed",
        "processed_count" => $success_count,
        "errors" => $errors
    ]);

} catch (Exception $e) {
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server Error: " . $e->getMessage()
    ]);
}
?>
