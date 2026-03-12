<?php
// api/tahfidz/get_assessment_types.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/db_mysqli.php';

try {
    $types = [];
    $query = "SELECT id, name, description FROM tahfidz_assessment_types WHERE is_active = 1 ORDER BY name ASC";
    
    $result = $mysqli->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $types[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($types),
        "data" => $types
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
