<?php
// api/tahfidz/get_memorization.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/db_mysqli.php';

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$date = isset($_GET['date']) ? $_GET['date'] : null;
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;

try {
    $memorization_records = [];
    $query = "SELECT m.*, s.nama_siswa as student_name, s.kelas, s.tingkat,
                     e.full_name as teacher_name,
                     m.surah_start as surah_name,
                     m.status as quality
              FROM tahfidz_memorization m
              LEFT JOIN students s ON m.student_id = s.id
              LEFT JOIN employees e ON m.teacher_id = e.id
              WHERE 1=1";

    $params = [];
    $types = "";

    if ($date) {
        $query .= " AND m.date = ?";
        $params[] = $date;
        $types .= "s";
    }

    if ($student_id) {
        $query .= " AND m.student_id = ?";
        $params[] = $student_id;
        $types .= "i";
    }

    if ($teacher_id) {
        $query .= " AND m.teacher_id = ?";
        $params[] = $teacher_id;
        $types .= "i";
    }

    $query .= " ORDER BY m.date DESC, m.created_at DESC";

    $stmt = $mysqli->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $memorization_records[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($memorization_records),
        "data" => $memorization_records
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
