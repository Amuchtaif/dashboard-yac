<?php
// api/tahfidz/get_active_academic_year.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

try {
    $query = "SELECT id, name FROM academic_years WHERE is_active = 1 LIMIT 1";
    $result = $mysqli->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            "success" => true,
            "data" => [
                "id" => (int)$row['id'],
                "name" => $row['name']
            ]
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "data" => [
                "id" => 1,
                "name" => "2025/2026"
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
