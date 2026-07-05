<?php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

if ($method === 'GET') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit_param = $_GET['limit'] ?? '10';
    $is_all = ($limit_param === 'all' || (int)$limit_param <= 0);
    
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';
    $is_active = $_GET['is_active'] ?? '';
    
    $where = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where .= " AND group_name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    if (!empty($type)) {
        $where .= " AND group_type = ?";
        $params[] = $type;
        $types .= "s";
    }
    if ($is_active !== '') {
        $where .= " AND is_active = ?";
        $params[] = (int)$is_active;
        $types .= "i";
    }
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total FROM employee_groups $where";
    $stmt = $mysqli->prepare($count_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Fetch data
    if ($is_all) {
        $sql = "SELECT * FROM employee_groups $where ORDER BY position ASC, id ASC";
        $stmt = $mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $limit = $total_records;
        $total_pages = 1;
    } else {
        $limit = (int)$limit_param;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM employee_groups $where ORDER BY position ASC, id ASC LIMIT ? OFFSET ?";
        $stmt = $mysqli->prepare($sql);
        
        $fetch_params = $params;
        $fetch_params[] = $limit;
        $fetch_params[] = $offset;
        $fetch_types = $types . "ii";
        
        $stmt->bind_param($fetch_types, ...$fetch_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_pages = ceil($total_records / $limit);
    }
    
    $groups = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Map group_name and group_type for frontend compatibility
            $row['name'] = $row['group_name'];
            $row['type'] = $row['group_type'];
            $groups[] = $row;
        }
    }
    
    echo json_encode([
        "status" => "success",
        "success" => true,
        "data" => $groups,
        "meta" => [
            "page" => $page,
            "limit" => $limit,
            "total_records" => (int)$total_records,
            "total_pages" => (int)$total_pages
        ]
    ]);
    exit;

} elseif ($method === 'POST') {
    // Create new employee group
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
        exit;
    }
    
    $group_name = $input['group_name'] ?? '';
    $group_type = $input['group_type'] ?? '';
    $description = $input['description'] ?? '';
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
    
    // Validation
    if (empty($group_name) || !in_array($group_type, ['dynamic', 'manual'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "success" => false, "message" => "Validation failed: group_name is required and group_type must be dynamic or manual."]);
        exit;
    }
    
    // Check if group name exists
    $check_stmt = $mysqli->prepare("SELECT id FROM employee_groups WHERE group_name = ? LIMIT 1");
    if ($check_stmt) {
        $check_stmt->bind_param("s", $group_name);
        $check_stmt->execute();
        if ($check_stmt->get_result()->fetch_assoc()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "success" => false, "message" => "Group name already exists."]);
            exit;
        }
    }
    
    $mysqli->begin_transaction();
    try {
        // Calculate next position for the new group
        $pos_res = $mysqli->query("SELECT COALESCE(MAX(position), 0) AS max_pos FROM employee_groups");
        $next_position = 1;
        if ($pos_res) {
            $pos_row = $pos_res->fetch_assoc();
            $next_position = (int)$pos_row['max_pos'] + 1;
        }

        $stmt = $mysqli->prepare("INSERT INTO employee_groups (group_name, group_type, description, is_active, position) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $mysqli->error);
        }
        $stmt->bind_param("sssii", $group_name, $group_type, $description, $is_active, $next_position);
        if (!$stmt->execute()) {
            throw new Exception("Execute statement failed: " . $stmt->error);
        }
        $group_id = $stmt->insert_id;
        $stmt->close();
        
        // Save Rules if Dynamic
        if ($group_type === 'dynamic') {
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
                        $r_stmt->bind_param("isss", $group_id, $field_name, $operator, $field_value);
                        $r_stmt->execute();
                    }
                }
                $r_stmt->close();
            }
        } else {
            // Save Members if Manual
            $employee_ids = $input['employee_ids'] ?? [];
            if (is_array($employee_ids) && !empty($employee_ids)) {
                $m_stmt = $mysqli->prepare("INSERT INTO employee_group_members (group_id, employee_id) VALUES (?, ?)");
                if (!$m_stmt) {
                    throw new Exception("Members prepare failed: " . $mysqli->error);
                }
                foreach ($employee_ids as $emp_id) {
                    $emp_id = (int)$emp_id;
                    $m_stmt->bind_param("ii", $group_id, $emp_id);
                    $m_stmt->execute();
                }
                $m_stmt->close();
            }
        }
        
        $mysqli->commit();
        
        echo json_encode([
            "status" => "success",
            "success" => true,
            "message" => "Employee group created successfully.",
            "data" => [
                "id" => $group_id,
                "group_name" => $group_name,
                "group_type" => $group_type,
                "description" => $description,
                "is_active" => $is_active
            ]
        ]);
        
    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "success" => false, 
            "message" => "Failed to create employee group: " . $e->getMessage()
        ]);
    }
    exit;

} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}
