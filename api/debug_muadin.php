<?php
// Check Muadin account specifically by name search
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Find Muadin by name
    $query = "SELECT 
                e.id, 
                e.full_name, 
                e.email,
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
              WHERE e.full_name LIKE '%Muadin%'
              ORDER BY e.id";
    
    $stmt = $conn->query($query);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true,
        "count" => count($employees),
        "employees" => $employees
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
