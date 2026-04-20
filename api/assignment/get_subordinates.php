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

require_once dirname(__DIR__, 2) . '/config/database.php';

try {
    /** @var \Database $database */
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
    $userLevel = (int)($supervisor['position_level'] ?? 99);
    $division_id = $supervisor['division_id'];
    $unit_id = $supervisor['unit_id'];

    $query = "SELECT e.id, e.full_name, p.name as position_name
              FROM employees e
              LEFT JOIN positions p ON e.position_id = p.id
              WHERE e.status = 'active'";
    
    $params = [];

    if ($is_admin) {
        // Administrator: Tampilkan semua pegawai aktif kecuali diri sendiri
        $query .= " AND e.id != :supervisor_id";
        $params[':supervisor_id'] = $supervisor_id;
    } else if ($userLevel === 1) {
        // Mudir (Muksin): Tampilkan semua Kepala Bidang (Level 2)
        $query .= " AND p.level = 2";
    } else if ($userLevel === 2) {
        // Kepala Bidang (Kabid): Tampilkan Kepala Unit/Sub (Level 3) 
        // dan Staff Langsung di bawah divisi (Posisi 'Staf' dengan unit_id kosong)
        $query .= " AND e.division_id = :division_id 
                    AND e.id != :supervisor_id
                    AND (
                        p.level = 3 
                        OR (p.name = 'Staf' AND (e.unit_id IS NULL OR e.unit_id = 0))
                    )";
        $params[':division_id'] = $division_id;
        $params[':supervisor_id'] = $supervisor_id;
    } else if ($userLevel === 3) {
        // Kepala Unit/Sub: Tampilkan semua pegawai dalam satu unit
        $query .= " AND e.unit_id = :unit_id AND e.id != :supervisor_id";
        $params[':unit_id'] = $unit_id;
        $params[':supervisor_id'] = $supervisor_id;
    } else {
        // Level lain: Hanya bisa menugaskan ke diri sendiri atau list kosong
        $query .= " AND e.id = :supervisor_id";
        $params[':supervisor_id'] = $supervisor_id;
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
