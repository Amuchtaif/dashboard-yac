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

    // 2. Filter students
    if (!empty($activeYear)) {
        // Normalize formats to cover slash vs hyphen differences (e.g. 2024/2025 vs 2024-2025)
        $yearSlash = str_replace('-', '/', $activeYear);
        $yearHyphen = str_replace('/', '-', $activeYear);
        
        // Use Prepared Statement for safety
        $stmt = $mysqli->prepare("SELECT * FROM students WHERE tahun_ajaran = ? OR tahun_ajaran = ? ORDER BY nama_siswa ASC");
        if ($stmt) {
            $stmt->bind_param("ss", $yearSlash, $yearHyphen);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            $stmt->close();
        } else {
             throw new Exception("Failed to prepare statement: " . $mysqli->error);
        }
    } else {
        // Option: if no active year determined, return empty or all?
        // User request specifically says "based on active year", so returning empty is safer if none active.
        // Or we could try to just fetch all if logic fails, but let's stick to the rule.
        // $students = []; 
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
