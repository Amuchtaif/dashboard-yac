<?php
// api/tahfidz/get_student_attendance.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// Include database connection
// Adjust path if necessary based on your folder structure
// Assuming this file is in api/tahfidz/ and config is in config/
include_once '../../config/db_mysqli.php';

$date = isset($_GET['date']) ? $_GET['date'] : null;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

try {
    $attendance_records = [];
    $query = "SELECT ta.*, s.name as student_name, s.class_grade 
              FROM tahfidz_attendance ta 
              LEFT JOIN students s ON ta.student_id = s.id 
              WHERE 1=1";

    $params = [];
    $types = "";

    if ($date) {
        $query .= " AND ta.date = ?";
        $params[] = $date;
        $types .= "s";
    }

    if ($student_id) {
        $query .= " AND ta.student_id = ?";
        $params[] = $student_id;
        $types .= "i";
    }

    $query .= " ORDER BY ta.date DESC, s.name ASC";

    if (isset($mysqli)) {
        $stmt = $mysqli->prepare($query);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }

        echo json_encode([
            "success" => true,
            "count" => count($attendance_records),
            "data" => $attendance_records
        ]);
    } else {
        throw new Exception("Database connection failed");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
