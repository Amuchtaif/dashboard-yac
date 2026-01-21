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
    // Logic: Fetch all permit records where approver_id matches AND status is 'Pending'
    // Joins: employees (to get full_name), units (name), divisions (name)
    // Ordering: Created Oldest First (created_at ASC)

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
            e.full_name as employee_name,
            u.name as unit_name,
            d.name as division_name,
            pos.name as position_name
        FROM permits p
        JOIN employees e ON p.employee_id = e.id
        LEFT JOIN positions pos ON e.position_id = pos.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN divisions d ON e.division_id = d.id
        WHERE p.approver_id = :aid 
          AND p.status = 'Pending'
        ORDER BY p.created_at ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':aid', $approver_id);
    $stmt->execute();

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