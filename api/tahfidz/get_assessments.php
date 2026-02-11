<?php
// api/tahfidz/get_assessments.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/db_mysqli.php';

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$month = isset($_GET['month']) ? $_GET['month'] : null; // YYYY-MM
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;

try {
    $assessments = [];
    $query = "SELECT a.*, s.name as student_name 
              FROM tahfidz_assessments a
              LEFT JOIN students s ON a.student_id = s.id
              WHERE 1=1";

    $params = [];
    $types = "";

    if ($student_id) {
        $query .= " AND a.student_id = ?";
        $params[] = $student_id;
        $types .= "i";
    }

    if ($month) {
        $query .= " AND DATE_FORMAT(a.assessment_date, '%Y-%m') = ?";
        $params[] = $month;
        $types .= "s";
    }

    if ($teacher_id) {
        $query .= " AND a.teacher_id = ?";
        $params[] = $teacher_id;
        $types .= "i";
    }

    $query .= " ORDER BY a.assessment_date DESC";

    $stmt = $mysqli->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $assessments[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($assessments),
        "data" => $assessments
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
