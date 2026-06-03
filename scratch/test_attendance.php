<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set directory context to api/tahfidz so relative includes work
chdir(__DIR__ . '/../api/tahfidz');

include_once '../../config/db_mysqli.php';

date_default_timezone_set('Asia/Jakarta');

$teacher_id = 1; // Simulated teacher_id
$action = 'check_in';
$full_time = '2026-06-03 15:43:44';
$date = date('Y-m-d', strtotime($full_time));
$now = date('H:i:s', strtotime($full_time));
$notes = 'Sore';

echo "Simulating check-in for Teacher ID: $teacher_id, Date: $date, Time: $now, Notes: $notes\n";

// Check Permission
include_once '../../config/permission.php';
if (!hasPermission($teacher_id, 'access_tahfidz')) {
    echo "Access Denied by hasPermission\n";
    exit;
}

try {
    if ($action === 'check_in') {
        // Check if already checked in FOR THIS SESSION
        $check = $mysqli->prepare("SELECT id FROM tahfidz_teacher_attendance WHERE teacher_id = ? AND date = ? AND notes = ?");
        if (!$check) {
            throw new Exception("Check Prepare failed: " . $mysqli->error);
        }
        $check->bind_param("iss", $teacher_id, $date, $notes);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            echo "Sesi $notes sudah dibuka hari ini\n";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO tahfidz_teacher_attendance (teacher_id, date, check_in_time, status, notes) VALUES (?, ?, ?, 'Hadir', ?)");
            if (!$stmt) {
                throw new Exception("Insert Prepare failed: " . $mysqli->error);
            }
            $stmt->bind_param("isss", $teacher_id, $date, $now, $notes);
            $stmt->execute();
            echo "Halaqoh $notes berhasil dibuka\n";
        }
    }
} catch (Exception $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
}
