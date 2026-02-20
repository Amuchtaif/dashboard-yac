<?php
// api/tahfidz/get_students.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit;
}

try {
    $students = [];
    
    // 1. Get active academic year
    $activeYear = "";
    $yearQuery = "SELECT name FROM academic_years WHERE is_active = 1 LIMIT 1";
    $yearResult = $mysqli->query($yearQuery);
    
    if ($yearResult && $yearResult->num_rows > 0) {
        $yearRow = $yearResult->fetch_assoc();
        $activeYear = $yearRow['name'];
    }

    // 2. Fetch all students (removed strict academic year filtering to show all data)
    $query = "SELECT * FROM students ORDER BY nama_siswa ASC";
    $result = $mysqli->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } else {
        throw new Exception("Error executing query: " . $mysqli->error);
    }

    echo json_encode([
        "success" => true,
        "count" => count($students),
        "data" => $students,
        "active_year_debug" => $activeYear 
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
