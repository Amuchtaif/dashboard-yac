<?php
// api/get_approval_list.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!isset($_GET['user_id'])) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "User ID (Approver) required"]);
        exit();
    }

    $approver_id = $_GET['user_id'];

    // 0. Fetch Approver Info (Level)
    $stmtUser = $conn->prepare("
        SELECT p.level 
        FROM employees e 
        JOIN positions p ON e.position_id = p.id 
        WHERE e.id = :id
    ");
    $stmtUser->execute([':id' => $approver_id]);
    $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$uData) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "User (Approver) not found"]);
        exit();
    }

    $level = (int) $uData['level'];

    // 1. Visibility Filter
    if ($level === 1) {
        $whereClause = "(p.approver_id = :aid OR pos.level = 2) AND p.employee_id != :aid";
        $params = [':aid' => $approver_id];
    } else {
        $whereClause = "p.approver_id = :aid AND p.employee_id != :aid";
        $params = [':aid' => $approver_id];
    }

    $query = "
        SELECT 
            p.id, 
            p.permit_type, 
            p.start_date, 
            p.end_date, 
            p.reason, 
            p.created_at, 
            p.status,
            p.attachment,
            p.is_hourly,
            p.start_time,
            p.end_time,
            e.full_name as employee_name,
            u.name as unit_name,
            d.name as division_name,
            pos.name as position_name
        FROM permits p
        JOIN employees e ON p.employee_id = e.id
        LEFT JOIN positions pos ON e.position_id = pos.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN divisions d ON e.division_id = d.id
        WHERE $whereClause 
        ORDER BY (p.status = 'Pending') DESC, p.start_date DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);

    $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultData = [];
    foreach ($approvals as $row) {
        $location = $row['unit_name'] ? $row['unit_name'] : $row['division_name'];
        $row['employee_details'] = $row['employee_name'] . ($location ? " - " . $location : "");
        $resultData[] = $row;
    }

    ob_clean();
    echo json_encode([
        "success" => true,
        "data" => $resultData
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}

?>