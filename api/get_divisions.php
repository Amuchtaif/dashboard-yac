<?php
// api/get_divisions.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/db_mysqli.php';

try {
    $query = "SELECT id, name FROM divisions ORDER BY name ASC";
    $result = $mysqli->query($query);

    $divisions = [];
    // Add virtual division for Pengurus Inti
    $divisions[] = ["id" => "pengurus_inti", "name" => "Pengurus Inti"];

    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $divisions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$mysqli->close();
?>