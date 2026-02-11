<?php
// api/tahfidz/submit_teacher_attendance.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/db_mysqli.php';

$input = json_decode(file_get_contents("php://input"), true);
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;
$action = isset($input['action']) ? $input['action'] : 'check_in'; // check_in, check_out
$date = date('Y-m-d');
$now = date('H:i:s');
$notes = isset($input['notes']) ? $input['notes'] : '';

if (!$teacher_id) {
    echo json_encode(["success" => false, "message" => "Teacher ID required"]);
    exit;
}

try {
    if ($action === 'check_in') {
        // Check if already checked in
        $check = $mysqli->prepare("SELECT id FROM tahfidz_teacher_attendance WHERE teacher_id = ? AND date = ?");
        $check->bind_param("is", $teacher_id, $date);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            echo json_encode(["success" => false, "message" => "Already checked in today"]);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO tahfidz_teacher_attendance (teacher_id, date, check_in_time, status, notes) VALUES (?, ?, ?, 'Hadir', ?)");
            $stmt->bind_param("isss", $teacher_id, $date, $now, $notes);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Check-in successful"]);
        }
    } elseif ($action === 'check_out') {
        $stmt = $mysqli->prepare("UPDATE tahfidz_teacher_attendance SET check_out_time = ? WHERE teacher_id = ? AND date = ?");
        $stmt->bind_param("sis", $now, $teacher_id, $date);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Check-out successful"]);
        } else {
            echo json_encode(["success" => false, "message" => "No check-in record found for today"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action"]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
