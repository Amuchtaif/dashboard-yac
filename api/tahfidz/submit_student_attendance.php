<?php
// api/tahfidz/submit_student_attendance.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

$date = isset($input['date']) ? $input['date'] : date('Y-m-d');
$session = isset($input['session']) ? $input['session'] : 'Pagi';
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;
$students = isset($input['students']) ? $input['students'] : [];

if (empty($students)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "No student data provided"]);
    exit;
}

$success_count = 0;
$errors = [];

try {
    $mysqli->begin_transaction();

    // Prepare statement for insert/update
    // Using ON DUPLICATE KEY UPDATE to handle re-submissions for the same day/session
    $sql = "INSERT INTO tahfidz_attendance (student_id, date, status, session, teacher_id) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE status = VALUES(status), teacher_id = VALUES(teacher_id)";
    
    $stmt = $mysqli->prepare($sql);

    foreach ($students as $student) {
        if (!isset($student['student_id']) || !isset($student['status'])) {
            $errors[] = "Missing id or status for one record";
            continue; // Skip invalid
        }

        $s_id = $student['student_id'];
        $status = $student['status'];

        $stmt->bind_param("isssi", $s_id, $date, $status, $session, $teacher_id);
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $errors[] = "Failed for student ID $s_id: " . $stmt->error;
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
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server Error: " . $e->getMessage()
    ]);
}
?>
