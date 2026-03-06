<?php
// api/assignment/get_subordinates.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Support both GET and POST
    $supervisor_id = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $supervisor_id = $input['supervisor_id'] ?? ($_POST['supervisor_id'] ?? null);
    } else {
        $supervisor_id = $_GET['supervisor_id'] ?? null;
    }

    if (!$supervisor_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Parameter supervisor_id wajib diisi."]);
        exit();
    }

    // Get supervisor's position level and info
    $sup_query = "SELECT e.id, e.position_id, p.name as position_name, p.level as position_level,
                         e.division_id, e.unit_id
                  FROM employees e
                  LEFT JOIN positions p ON e.position_id = p.id
                  WHERE e.id = :id LIMIT 1";
    $sup_stmt = $db->prepare($sup_query);
    $sup_stmt->execute([':id' => $supervisor_id]);
    $supervisor = $sup_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supervisor) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Supervisor tidak ditemukan."]);
        exit();
    }

    $is_admin = ($supervisor['position_name'] === 'Administrator');
    $sup_level = (int)($supervisor['position_level'] ?? 99);

    // Build subordinates query
    // - Administrator: can assign to ALL active employees (except themselves)
    // - Others: can assign to employees with same or higher level number (lower in hierarchy)
    //   and exclude themselves
    $query = "SELECT e.id, e.full_name, p.name as position_name
              FROM employees e
              LEFT JOIN positions p ON e.position_id = p.id
              WHERE e.status = 'active' AND e.id != :supervisor_id";

    $params = [':supervisor_id' => $supervisor_id];

    if (!$is_admin) {
        $query .= " AND p.level >= :sup_level";
        $params[':sup_level'] = $sup_level;
    }

    $query .= " ORDER BY e.full_name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $subordinates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure clean output
    foreach ($subordinates as &$sub) {
        $sub['id'] = (int)$sub['id'];
        $sub['position_name'] = $sub['position_name'] ?? '-';
    }

    echo json_encode([
        "success" => true,
        "count" => count($subordinates),
        "data" => $subordinates
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Internal Server Error: " . $e->getMessage()]);
}
