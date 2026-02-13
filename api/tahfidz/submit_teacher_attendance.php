<?php
// api/tahfidz/submit_teacher_attendance.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/db_mysqli.php';

date_default_timezone_set('Asia/Jakarta');

$input = json_decode(file_get_contents("php://input"), true);
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;
$action = isset($input['action']) ? $input['action'] : 'check_in'; // check_in, check_out

// Prioritize time from app, fallback to server time
$full_time = isset($input['time']) ? $input['time'] : date('Y-m-d H:i:s');
$date = date('Y-m-d', strtotime($full_time));
$now = date('H:i:s', strtotime($full_time));

$notes = isset($input['notes']) ? $input['notes'] : '';

if (!$teacher_id) {
    echo json_encode(["success" => false, "message" => "Teacher ID required"]);
    exit;
}

try {
    if ($action === 'check_in') {
        // Check if already checked in FOR THIS SESSION
        $check = $mysqli->prepare("SELECT id FROM tahfidz_teacher_attendance WHERE teacher_id = ? AND date = ? AND notes = ?");
        $check->bind_param("iss", $teacher_id, $date, $notes);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            echo json_encode(["success" => false, "message" => "Sesi $notes sudah dibuka hari ini"]);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO tahfidz_teacher_attendance (teacher_id, date, check_in_time, status, notes) VALUES (?, ?, ?, 'Hadir', ?)");
            $stmt->bind_param("isss", $teacher_id, $date, $now, $notes);
            $stmt->execute();
            echo json_encode(["success" => true, "message" => "Halaqoh $notes berhasil dibuka"]);
        }
    } elseif ($action === 'check_out') {
        // 1. Search for a specific ACTIVE session based on Teacher, Date, and Session Name (notes)
        $stmt = $mysqli->prepare("SELECT id FROM tahfidz_teacher_attendance 
                                 WHERE teacher_id = ? 
                                 AND date = ? 
                                 AND notes = ? 
                                 AND check_out_time IS NULL 
                                 ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("iss", $teacher_id, $date, $notes);
        $stmt->execute();
        $result = $stmt->get_result();
        $activeSesi = $result->fetch_assoc();

        if ($activeSesi) {
            // 2. If found, update the check_out_time using $now (which is derived from application time)
            $update = $mysqli->prepare("UPDATE tahfidz_teacher_attendance SET check_out_time = ? WHERE id = ?");
            $update->bind_param("si", $now, $activeSesi['id']);
            $update->execute();
            
            if ($update->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => "Halaqoh $notes berhasil ditutup"]);
            } else {
                echo json_encode(['success' => false, 'message' => "Gagal menutup halaqoh $notes"]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => "Tidak ada sesi $notes yang aktif atau sudah ditutup"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Aksi tidak valid"]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
