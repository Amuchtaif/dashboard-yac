<?php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
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

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

$employee_ids_collected = [];
$group_count = 0;
$individual_count = 0;

// Helper function to resolve dynamic group rules into employee IDs
function resolveDynamicGroupRules($mysqli, $rules) {
    if (empty($rules)) return [];
    
    $query = "SELECT id FROM employees WHERE 1=1 ";
    $params = [];
    $types = "";
    
    foreach ($rules as $rule) {
        $field_name = $rule['field_name'] ?? $rule['field'] ?? '';
        $operator = $rule['operator'] ?? '=';
        $field_value = $rule['field_value'] ?? $rule['value'] ?? '';
        
        if (strtolower($field_name) === 'jam ngajar di unit') {
            $subQueryOperator = ($operator === '!=') ? 'NOT IN' : 'IN';
            $query .= " AND id $subQueryOperator (SELECT DISTINCT cs.employee_id FROM class_schedules cs JOIN grade_levels gl ON cs.grade_level_id = gl.id JOIN education_units eu ON gl.education_unit_id = eu.id WHERE eu.operational_unit_id = ?) ";
            $params[] = $field_value;
            $types .= "s";
        } else {
            $col = "";
            switch(strtolower($field_name)) {
                case 'unit': $col = 'unit_id'; break;
                case 'gender': $col = 'gender'; break;
                case 'jabatan': $col = 'position_id'; break;
                case 'departemen': $col = 'division_id'; break;
                case 'status karyawan': $col = 'status'; break;
                default: $col = $field_name;
            }
            
            $allowed_operators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'IN'];
            if (preg_match('/^[a-zA-Z0-9_]+$/', $col) && in_array(strtoupper($operator), $allowed_operators)) {
                $query .= " AND $col " . strtoupper($operator) . " ? ";
                $params[] = $field_value;
                $types .= "s";
            }
        }
    }
    
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
        return $ids;
    }
    return [];
}

// 1. Check if group_ids and/or employee_ids are provided (for multi-selection preview)
if (isset($input['group_ids']) || isset($input['employee_ids'])) {
    $group_ids = isset($input['group_ids']) && is_array($input['group_ids']) ? $input['group_ids'] : [];
    $employee_ids = isset($input['employee_ids']) && is_array($input['employee_ids']) ? $input['employee_ids'] : [];
    
    $group_count = count($group_ids);
    $individual_count = count($employee_ids);
    
    // Add individual employee IDs directly
    foreach ($employee_ids as $emp_id) {
        $employee_ids_collected[] = (int)$emp_id;
    }
    
    // Resolve group IDs
    foreach ($group_ids as $g_id) {
        $g_id = (int)$g_id;
        // Check if group is manual or dynamic
        $g_stmt = $mysqli->prepare("SELECT group_type FROM employee_groups WHERE id = ?");
        $g_stmt->bind_param("i", $g_id);
        $g_stmt->execute();
        $group = $g_stmt->get_result()->fetch_assoc();
        
        if ($group) {
            if ($group['group_type'] === 'manual') {
                // Fetch members
                $m_stmt = $mysqli->prepare("SELECT employee_id FROM employee_group_members WHERE group_id = ?");
                $m_stmt->bind_param("i", $g_id);
                $m_stmt->execute();
                $m_res = $m_stmt->get_result();
                while ($m_row = $m_res->fetch_assoc()) {
                    $employee_ids_collected[] = (int)$m_row['employee_id'];
                }
            } else if ($group['group_type'] === 'dynamic') {
                // Fetch rules
                $r_stmt = $mysqli->prepare("SELECT * FROM employee_group_rules WHERE group_id = ?");
                $r_stmt->bind_param("i", $g_id);
                $r_stmt->execute();
                $r_res = $r_stmt->get_result();
                $rules = [];
                while ($r_row = $r_res->fetch_assoc()) {
                    $rules[] = $r_row;
                }
                $dynamic_ids = resolveDynamicGroupRules($mysqli, $rules);
                $employee_ids_collected = array_merge($employee_ids_collected, $dynamic_ids);
            }
        }
    }
    
    // Deduplicate
    $employee_ids_collected = array_unique($employee_ids_collected);
    
    if (empty($employee_ids_collected)) {
        echo json_encode([
            "status" => "success",
            "success" => true,
            "data" => [
                "total" => 0,
                "members" => [],
                "group_count" => $group_count,
                "individual_count" => $individual_count
            ]
        ]);
        exit;
    }
    
    // Query detailed employee list
    $placeholders = implode(',', array_fill(0, count($employee_ids_collected), '?'));
    $types = str_repeat('i', count($employee_ids_collected));
    
    $query = "SELECT e.id, e.full_name, e.nik, e.unit_id, e.division_id, e.status,
                     u.name as unit_name, d.name as division_name, p.name as position_name
              FROM employees e
              LEFT JOIN divisions d ON e.division_id = d.id
              LEFT JOIN units u ON e.unit_id = u.id
              LEFT JOIN positions p ON e.position_id = p.id
              WHERE e.id IN ($placeholders)
              ORDER BY e.full_name ASC";
              
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$employee_ids_collected);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        
        echo json_encode([
            "status" => "success",
            "success" => true,
            "data" => [
                "total" => count($members),
                "members" => $members,
                "group_count" => $group_count,
                "individual_count" => $individual_count
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "success" => false,
            "message" => "Database error: " . $mysqli->error
        ]);
    }
    exit;
}

// 2. Legacy behaviour (Single group_id or rules preview)
$rules = [];
if (isset($input['group_id'])) {
    $group_id = (int)$input['group_id'];
    $stmt = $mysqli->prepare("SELECT * FROM employee_group_rules WHERE group_id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rules[] = $row;
    }
} elseif (isset($input['rules']) && is_array($input['rules'])) {
    $rules = $input['rules'];
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Validation failed: group_id, rules array, group_ids, or employee_ids is required"]);
    exit;
}

if (empty($rules)) {
    echo json_encode([
        "success" => true,
        "data" => [
            "total" => 0,
            "members" => [],
            "filter_summary" => "No rules applied"
        ]
    ]);
    exit;
}

$query = "SELECT e.id, e.full_name, e.nik, e.unit_id, e.division_id, e.status,
                 u.name as unit_name, d.name as division_name, p.name as position_name 
          FROM employees e 
          LEFT JOIN divisions d ON e.division_id = d.id
          LEFT JOIN units u ON e.unit_id = u.id
          LEFT JOIN positions p ON e.position_id = p.id
          WHERE 1=1 ";
$params = [];
$types = "";
$summary = [];

foreach ($rules as $rule) {
    $field_name = $rule['field_name'] ?? $rule['field'] ?? '';
    $operator = $rule['operator'] ?? '=';
    $field_value = $rule['field_value'] ?? $rule['value'] ?? '';
    
    if (strtolower($field_name) === 'jam ngajar di unit') {
        $subQueryOperator = ($operator === '!=') ? 'NOT IN' : 'IN';
        $query .= " AND e.id $subQueryOperator (SELECT DISTINCT cs.employee_id FROM class_schedules cs JOIN grade_levels gl ON cs.grade_level_id = gl.id JOIN education_units eu ON gl.education_unit_id = eu.id WHERE eu.operational_unit_id = ?) ";
        $params[] = $field_value;
        $types .= "s";
        $summary[] = "$field_name $operator $field_value";
    } else {
        $col = "";
        switch(strtolower($field_name)) {
            case 'unit': $col = 'e.unit_id'; break;
            case 'gender': $col = 'e.gender'; break;
            case 'jabatan': $col = 'e.position_id'; break;
            case 'departemen': $col = 'e.division_id'; break;
            case 'status karyawan': $col = 'e.status'; break;
            default: $col = 'e.' . $field_name;
        }
        
        $allowed_operators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'IN'];
        if (preg_match('/^[e\.a-zA-Z0-9_]+$/', $col) && in_array(strtoupper($operator), $allowed_operators)) {
            $query .= " AND $col " . strtoupper($operator) . " ? ";
            $params[] = $field_value;
            $types .= "s";
            $summary[] = "$field_name $operator $field_value";
        }
    }
}

$stmt = $mysqli->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "success" => true,
        "data" => [
            "total" => count($members),
            "members" => $members,
            "filter_summary" => implode(" AND ", $summary)
        ]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Failed to execute query: " . $mysqli->error,
        "query" => $query
    ]);
}
exit;
