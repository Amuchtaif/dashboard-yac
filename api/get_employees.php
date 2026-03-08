<?php
// api/get_employees.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/db_mysqli.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

try {
    // Base Query
    // Base Query
    $sql = "SELECT e.id, e.full_name, e.division_id, d.name as division_name, u.name as unit_name, p.name as position_name
            FROM employees e
            LEFT JOIN divisions d ON e.division_id = d.id
            LEFT JOIN units u ON e.unit_id = u.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.status = 'active'";

    // Exclude restricted positions (Kepala Bidang, Admin)
    $sql .= " AND (p.name IS NULL OR (p.name NOT LIKE '%Kepala Bidang%' AND p.name NOT LIKE '%Administrator%' AND p.name NOT LIKE '%Admin%'))";

    $params = [];
    $types = "";

    // Exclude current user if provided
    $current_user_id = $_GET['user_id'] ?? null;
    if ($current_user_id) {
        $sql .= " AND e.id != ?";
        $params[] = (int)$current_user_id;
        $types .= "i";
    }

    // Search Filter (by Name)
    if (!empty($search)) {
        $sql .= " AND e.full_name LIKE ?";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $types .= "s";
    }

    // Division and Unit Filter: Use AND for stricter scoping as requested
    if (!empty($division_id) && !empty($unit_id)) {
        $sql .= " AND e.division_id = ? AND e.unit_id = ?";
        $params[] = (int)$division_id;
        $params[] = (int)$unit_id;
        $types .= "ii";
    } elseif (!empty($unit_id)) {
        $sql .= " AND e.unit_id = ?";
        $params[] = (int)$unit_id;
        $types .= "i";
    } elseif (!empty($division_id)) {
        $sql .= " AND e.division_id = ?";
        $params[] = (int)$division_id;
        $types .= "i";
    }

    $sql .= " ORDER BY e.full_name ASC";

    // Support pagination dan ambil semua data
    $fetchAll = isset($_GET['all']) && $_GET['all'] === 'true';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

    if (!$fetchAll) {
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT $limit OFFSET $offset";
    }

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($employees),
        "data" => $employees
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}

$mysqli->close();
?>