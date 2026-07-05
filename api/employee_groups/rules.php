<?php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS");
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

if ($method === 'POST') {
    // Add rule
    $group_id = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $field_name = $input['field_name'] ?? '';
    $operator = $input['operator'] ?? '';
    $field_value = $input['field_value'] ?? '';
    
    if ($group_id <= 0 || empty($field_name) || empty($operator) || empty($field_value)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Validation failed: group_id, field_name, operator, and field_value are required"]);
        exit;
    }
    
    // Check if group is dynamic
    $g_stmt = $mysqli->prepare("SELECT group_type FROM employee_groups WHERE id = ?");
    $g_stmt->bind_param("i", $group_id);
    $g_stmt->execute();
    $group = $g_stmt->get_result()->fetch_assoc();
    
    if (!$group || $group['group_type'] !== 'dynamic') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Group not found or is not a dynamic group"]);
        exit;
    }
    
    $stmt = $mysqli->prepare("INSERT INTO employee_group_rules (group_id, field_name, operator, field_value) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $group_id, $field_name, $operator, $field_value);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Rule added successfully",
            "data" => [
                "id" => $stmt->insert_id,
                "group_id" => $group_id,
                "field_name" => $field_name,
                "operator" => $operator,
                "field_value" => $field_value
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to add rule: " . $stmt->error]);
    }
    exit;

} elseif ($method === 'PUT') {
    // Update rule
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : 0);
    $field_name = $input['field_name'] ?? '';
    $operator = $input['operator'] ?? '';
    $field_value = $input['field_value'] ?? '';
    
    if ($id <= 0 || empty($field_name) || empty($operator) || empty($field_value)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Validation failed: id, field_name, operator, and field_value are required"]);
        exit;
    }
    
    $stmt = $mysqli->prepare("UPDATE employee_group_rules SET field_name = ?, operator = ?, field_value = ? WHERE id = ?");
    $stmt->bind_param("sssi", $field_name, $operator, $field_value, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Rule updated successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Rule not found or no changes made"]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to update rule: " . $stmt->error]);
    }
    exit;

} elseif ($method === 'DELETE') {
    // Delete rule
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Rule ID is required"]);
        exit;
    }
    
    $stmt = $mysqli->prepare("DELETE FROM employee_group_rules WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Rule deleted successfully"]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Rule not found"]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to delete rule: " . $stmt->error]);
    }
    exit;

} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}
