<?php
// api/get_approval_list.php
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
    echo json_encode(["success" => false, "message" => "User ID (Approver) required"]);
    exit();
}

$approver_id = $_GET['user_id'];

try {
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
        echo json_encode(["success" => false, "message" => "User (Approver) not found"]);
        exit();
    }

    $level = (int) $uData['level'];

    // 1. Visibility Filter
    // Requirement: "level mudir (1) hanya menerima approve izin dari level kabid (2)"
    // We allow any level 1 user to see all pending from level 2 for shared visibility.
    if ($level === 1) {
        $whereClause = "pos.level = 2";
        $params = [];
    } else {
        // Normal assigned filter
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

    // Format data if needed (e.g., combine Unit/Div name)
    // Detailed Requirement: "Ahmad - Unit Satpam" (or Division if Unit is null)
    // We can do this in PHP iter to be safe.

    $resultData = [];
    foreach ($approvals as $row) {
        $location = $row['unit_name'] ? $row['unit_name'] : $row['division_name'];
        $row['employee_details'] = $row['employee_name'] . ($location ? " - " . $location : "");
        $resultData[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $resultData
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>