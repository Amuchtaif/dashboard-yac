<?php
// api/tahfidz/get_halaqah_groups.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../../config/db_mysqli.php';

try {
    $query = "SELECT hg.id, hg.group_name, hg.teacher_id, e.full_name as teacher_name,
                     (SELECT COUNT(*) FROM halaqah_members hm WHERE hm.group_id = hg.id) as member_count
              FROM halaqah_groups hg
              LEFT JOIN employees e ON hg.teacher_id = e.id
              ORDER BY LENGTH(hg.group_name) ASC, hg.group_name ASC";

    $result = $mysqli->query($query);
    $groups = [];

    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($groups),
        "data" => $groups
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
