<?php
// api/get_departments.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/db_mysqli.php';

try {
    // Only return departments that have at least one active employee
    $query = "SELECT d.id, d.name, COUNT(e.id) as employee_count 
              FROM departments d
              INNER JOIN employees e ON d.id = e.department_id
              WHERE e.status = 'active'
              GROUP BY d.id
              ORDER BY d.name ASC";
              
    $result = $mysqli->query($query);

    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $departments
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
