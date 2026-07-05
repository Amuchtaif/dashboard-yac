<?php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

if ($method === 'GET') {
    $group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
    
    if ($group_id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Group ID is required"]);
        exit;
    }
    
    $stmt = $mysqli->prepare("
        SELECT m.id, m.group_id, m.employee_id, m.created_at, 
               e.full_name, e.nik, e.unit_id, e.division_id
        FROM employee_group_members m
        JOIN employees e ON m.employee_id = e.id
        WHERE m.group_id = ?
    ");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "success" => true,
        "data" => $members
    ]);
    exit;

} elseif ($method === 'POST') {
    $group_id = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $employee_id = isset($input['employee_id']) ? (int)$input['employee_id'] : 0;
    
    if ($group_id <= 0 || $employee_id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "success" => false, "message" => "Validation failed: group_id and employee_id are required"]);
        exit;
    }
    
    // Check if group is manual
    $g_stmt = $mysqli->prepare("SELECT group_type FROM employee_groups WHERE id = ?");
    $g_stmt->bind_param("i", $group_id);
    $g_stmt->execute();
    $group = $g_stmt->get_result()->fetch_assoc();
    
    if (!$group || $group['group_type'] !== 'manual') {
        http_response_code(400);
        echo json_encode(["status" => "error", "success" => false, "message" => "Group not found or is not a manual group"]);
        exit;
    }
    
    // Check if employee is active
    $e_stmt = $mysqli->prepare("SELECT is_active FROM employees WHERE id = ?");
    $e_stmt->bind_param("i", $employee_id);
    $e_stmt->execute();
    $employee = $e_stmt->get_result()->fetch_assoc();
    
    if (!$employee) {
        http_response_code(400);
        echo json_encode(["status" => "error", "success" => false, "message" => "Employee not found"]);
        exit;
    }
    
    if (isset($employee['is_active']) && $employee['is_active'] == 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "success" => false, "message" => "Cannot add inactive employee"]);
        exit;
    }
    
    $stmt = $mysqli->prepare("INSERT INTO employee_group_members (group_id, employee_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $group_id, $employee_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "success" => true,
            "message" => "Member added successfully",
            "data" => [
                "id" => $stmt->insert_id,
                "group_id" => $group_id,
                "employee_id" => $employee_id
            ]
        ]);
    } else {
        // Handle duplicate entry (1062 is standard for duplicate key, but we just check error string)
        if ($stmt->errno === 1062) {
            http_response_code(400);
            echo json_encode(["status" => "error", "success" => false, "message" => "Employee is already a member of this group"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "success" => false, "message" => "Failed to add member: " . $stmt->error]);
        }
    }
    exit;

} elseif ($method === 'DELETE') {
    // Delete by member record id, or by group_id AND employee_id
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : 0);
    
    if ($id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM employee_group_members WHERE id = ?");
        $stmt->bind_param("i", $id);
    } else {
        $group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : (isset($input['group_id']) ? (int)$input['group_id'] : 0);
        $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : (isset($input['employee_id']) ? (int)$input['employee_id'] : 0);
        
        if ($group_id <= 0 || $employee_id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "success" => false, "message" => "Validation failed: id OR (group_id AND employee_id) is required"]);
            exit;
        }
        
        $stmt = $mysqli->prepare("DELETE FROM employee_group_members WHERE group_id = ? AND employee_id = ?");
        $stmt->bind_param("ii", $group_id, $employee_id);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["status" => "success", "success" => true, "message" => "Member removed successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "success" => false, "message" => "Member not found"]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "success" => false, "message" => "Failed to remove member: " . $stmt->error]);
    }
    exit;

} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}
