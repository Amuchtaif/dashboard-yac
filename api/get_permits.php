<?php
// api/get_permits.php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_GET['user_id'])) {
    echo json_encode(["success" => false, "message" => "User ID required"]);
    exit();
}

$user_id = $_GET['user_id'];

try {
    // Check Position Level
    $position_level = isset($_GET['position_level']) ? (int) $_GET['position_level'] : 0;

    // Get current month boundaries
    $firstDayOfMonth = date('Y-m-01 00:00:00');
    $lastDayOfMonth = date('Y-m-t 23:59:59');

    if ($position_level === 1) {
        // Level 1 (Director) sees ALL permits for current month
        $query = "SELECT p.*, 
                         e.full_name as employee_name,
                         u.name as unit_name,
                         d.name as division_name,
                         app.full_name as approver_name
                  FROM permits p 
                  LEFT JOIN employees e ON p.employee_id = e.id 
                  LEFT JOIN units u ON e.unit_id = u.id
                  LEFT JOIN divisions d ON e.division_id = d.id
                  LEFT JOIN employees app ON p.approved_by = app.id
                  WHERE p.created_at >= :start_date AND p.created_at <= :end_date
                  ORDER BY p.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':start_date', $firstDayOfMonth);
        $stmt->bindParam(':end_date', $lastDayOfMonth);
    } else {
        // Normal user sees only their own permits for current month
        $query = "SELECT p.*, app.full_name as approver_name 
                  FROM permits p 
                  LEFT JOIN employees app ON p.approved_by = app.id
                  WHERE p.employee_id = :uid 
                  AND p.created_at >= :start_date AND p.created_at <= :end_date
                  ORDER BY p.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':uid', $user_id);
        $stmt->bindParam(':start_date', $firstDayOfMonth);
        $stmt->bindParam(':end_date', $lastDayOfMonth);
    }

    $stmt->execute();

    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $permits
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>