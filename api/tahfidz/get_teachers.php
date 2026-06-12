<?php
// api/tahfidz/get_teachers.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Database configuration file not found or connection variable not set."]));
}

try {
    // Query to get teachers (pengampu). 
    // Assuming all active employees are potential pengampu for now, or you can filter by specific criteria.
    // We select necessary fields for AbsensiPengampuScreen: id, nama_lengkap, unit_name, foto.
    
    $query = "SELECT 
                e.id, 
                e.full_name as nama_lengkap, 
                e.full_name as name, 
                '' as foto,
                u.name as unit_name,
                p.name as position_name
              FROM employees e
              LEFT JOIN units u ON e.unit_id = u.id
              LEFT JOIN positions p ON e.position_id = p.id
              WHERE e.status = 'active' AND p.can_access_tahfidz = 1
              ORDER BY e.full_name ASC";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($teachers),
        "data" => $teachers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
