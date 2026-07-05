<?php
// api/add_meeting_participants.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/db_mysqli.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$meeting_id = isset($input['meeting_id']) ? (int)$input['meeting_id'] : 0;
$group_ids = isset($input['group_ids']) && is_array($input['group_ids']) ? $input['group_ids'] : [];
$employee_ids = isset($input['employee_ids']) && is_array($input['employee_ids']) ? $input['employee_ids'] : [];

if ($meeting_id <= 0) {
    echo json_encode(["success" => false, "message" => "meeting_id wajib diisi."]);
    exit();
}

$employee_ids_collected = [];

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

// Add individual employee IDs directly
foreach ($employee_ids as $emp_id) {
    $employee_ids_collected[] = (int)$emp_id;
}

// Resolve group IDs
foreach ($group_ids as $g_id) {
    $g_id = (int)$g_id;
    $g_stmt = $mysqli->prepare("SELECT group_type FROM employee_groups WHERE id = ?");
    $g_stmt->bind_param("i", $g_id);
    $g_stmt->execute();
    $group = $g_stmt->get_result()->fetch_assoc();
    
    if ($group) {
        if ($group['group_type'] === 'manual') {
            $m_stmt = $mysqli->prepare("SELECT employee_id FROM employee_group_members WHERE group_id = ?");
            $m_stmt->bind_param("i", $g_id);
            $m_stmt->execute();
            $m_res = $m_stmt->get_result();
            while ($m_row = $m_res->fetch_assoc()) {
                $employee_ids_collected[] = (int)$m_row['employee_id'];
            }
        } else if ($group['group_type'] === 'dynamic') {
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
    echo json_encode(["success" => false, "message" => "Pilih minimal 1 peserta baru untuk ditambahkan."]);
    exit();
}

$mysqli->begin_transaction();
$added_count = 0;

try {
    foreach ($employee_ids_collected as $emp_id) {
        // Check if already in meeting_participants
        $check_stmt = $mysqli->prepare("SELECT id FROM meeting_participants WHERE meeting_id = ? AND employee_id = ?");
        $check_stmt->bind_param("ii", $meeting_id, $emp_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        
        if (!$existing) {
            $insert_stmt = $mysqli->prepare("INSERT INTO meeting_participants (meeting_id, employee_id, status) VALUES (?, ?, 'invited')");
            $insert_stmt->bind_param("ii", $meeting_id, $emp_id);
            if (!$insert_stmt->execute()) {
                throw new Exception("Gagal menambahkan peserta ID: $emp_id - " . $insert_stmt->error);
            }
            $added_count++;
        }
    }
    
    $mysqli->commit();
    echo json_encode([
        "success" => true,
        "message" => "$added_count peserta baru berhasil ditambahkan.",
        "added_count" => $added_count
    ]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(["success" => false, "message" => "Terjadi kesalahan: " . $e->getMessage()]);
}

$mysqli->close();
?>
