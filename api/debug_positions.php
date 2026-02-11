<?php
// Debug script to check positions data
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Check positions table
    $positionsQuery = "SELECT * FROM positions ORDER BY level";
    $positionsStmt = $conn->query($positionsQuery);
    $positions = $positionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Check employees with their positions
    $employeesQuery = "SELECT 
                e.id, 
                e.full_name, 
                e.position_id,
                e.unit_id,
                e.division_id,
                p.name AS position_name,
                p.level AS position_level,
                u.name AS unit_name, 
                d.name AS division_name
              FROM employees e 
              LEFT JOIN positions p ON e.position_id = p.id
              LEFT JOIN units u ON e.unit_id = u.id 
              LEFT JOIN divisions d ON e.division_id = d.id
              ORDER BY p.level ASC, e.full_name";
    $employeesStmt = $conn->query($employeesQuery);
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true,
        "positions" => $positions,
        "employees" => $employees
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
