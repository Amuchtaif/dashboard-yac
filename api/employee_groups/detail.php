<?php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS");
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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Group ID is required"]);
    exit;
}

// Check if group exists
$stmt = $mysqli->prepare("SELECT * FROM employee_groups WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$group = $result->fetch_assoc();

if (!$group) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Employee group not found"]);
    exit;
}

if ($method === 'GET') {
    // Get rules if dynamic
    if ($group['group_type'] === 'dynamic') {
        $r_stmt = $mysqli->prepare("SELECT * FROM employee_group_rules WHERE group_id = ?");
        $r_stmt->bind_param("i", $id);
        $r_stmt->execute();
        $r_result = $r_stmt->get_result();
        $rules = [];
        while ($row = $r_result->fetch_assoc()) {
            // Map field_name to field, and field_value to value for frontend rules
            $row['field'] = $row['field_name'];
            $row['value'] = $row['field_value'];
            $rules[] = $row;
        }
        $group['rules'] = json_encode($rules);
    }
    
    // Map group_name and group_type for frontend compatibility
    $group['name'] = $group['group_name'];
    $group['type'] = $group['group_type'];
    
    echo json_encode([
        "status" => "success",
        "success" => true,
        "data" => $group
    ]);
    exit;

} elseif ($method === 'PUT') {
    // Update group
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "success" => false, "message" => "Invalid JSON input"]);
        exit;
    }
    
    $group_name = $input['group_name'] ?? $group['group_name'];
    $description = $input['description'] ?? $group['description'];
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : $group['is_active'];
    
    // Check if new group_name exists and is not the current group
    if ($group_name !== $group['group_name']) {
        $check_stmt = $mysqli->prepare("SELECT id FROM employee_groups WHERE group_name = ? AND id != ? LIMIT 1");
        $check_stmt->bind_param("si", $group_name, $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->fetch_assoc()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "success" => false, "message" => "Group name already exists."]);
            exit;
        }
    }
    
    $mysqli->begin_transaction();
    try {
        $u_stmt = $mysqli->prepare("UPDATE employee_groups SET group_name = ?, description = ?, is_active = ? WHERE id = ?");
        if (!$u_stmt) {
            throw new Exception("Prepare statement failed: " . $mysqli->error);
        }
        $u_stmt->bind_param("ssii", $group_name, $description, $is_active, $id);
        if (!$u_stmt->execute()) {
            throw new Exception("Execute statement failed: " . $u_stmt->error);
        }
        $u_stmt->close();
        
        $group_type = $group['group_type'];
        if ($group_type === 'dynamic') {
            // Delete old rules
            $mysqli->query("DELETE FROM employee_group_rules WHERE group_id = $id");
            
            $rules_input = $input['rules'] ?? '[]';
            $rules = is_array($rules_input) ? $rules_input : json_decode($rules_input, true);
            if (is_array($rules)) {
                $r_stmt = $mysqli->prepare("INSERT INTO employee_group_rules (group_id, field_name, operator, field_value) VALUES (?, ?, ?, ?)");
                if (!$r_stmt) {
                    throw new Exception("Rules prepare failed: " . $mysqli->error);
                }
                foreach ($rules as $rule) {
                    $field_name = $rule['field'] ?? $rule['field_name'] ?? '';
                    $operator = $rule['operator'] ?? '=';
                    $field_value = $rule['value'] ?? $rule['field_value'] ?? '';
                    if (!empty($field_name)) {
                        $r_stmt->bind_param("isss", $id, $field_name, $operator, $field_value);
                        $r_stmt->execute();
                    }
                }
                $r_stmt->close();
            }
        } else {
            // Delete old manual members
            $mysqli->query("DELETE FROM employee_group_members WHERE group_id = $id");
            
            $employee_ids = $input['employee_ids'] ?? [];
            if (is_array($employee_ids) && !empty($employee_ids)) {
                $m_stmt = $mysqli->prepare("INSERT INTO employee_group_members (group_id, employee_id) VALUES (?, ?)");
                if (!$m_stmt) {
                    throw new Exception("Members prepare failed: " . $mysqli->error);
                }
                foreach ($employee_ids as $emp_id) {
                    $emp_id = (int)$emp_id;
                    $m_stmt->bind_param("ii", $id, $emp_id);
                    $m_stmt->execute();
                }
                $m_stmt->close();
            }
        }
        
        $mysqli->commit();
        echo json_encode([
            "status" => "success",
            "success" => true,
            "message" => "Employee group updated successfully."
        ]);
        
    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "success" => false, 
            "message" => "Failed to update employee group: " . $e->getMessage()
        ]);
    }
    exit;

} elseif ($method === 'DELETE') {
    // Delete group
    $d_stmt = $mysqli->prepare("DELETE FROM employee_groups WHERE id = ?");
    $d_stmt->bind_param("i", $id);
    
    if ($d_stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Employee group deleted successfully."
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to delete employee group: " . $d_stmt->error]);
    }
    exit;

} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}
