<?php
// api/kabid/staff_attendance/list_staff.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
require_once __DIR__ . '/../../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $kabid_id = $_GET['user_id'] ?? null;
    if (!$kabid_id) {
        echo json_encode(["success" => false, "message" => "Parameter user_id (Kabid ID) wajib diisi."]);
        exit;
    }

    // Ambil divisi Kabid
    $stmtDiv = $conn->prepare("SELECT division_id FROM employees WHERE id = ?");
    $stmtDiv->execute([$kabid_id]);
    $kabid = $stmtDiv->fetch(PDO::FETCH_ASSOC);

    if (!$kabid || !$kabid['division_id']) {
        echo json_encode(["success" => false, "message" => "Kabid tidak ditemukan atau tidak memiliki divisi."]);
        exit;
    }

    $divId = $kabid['division_id'];

    // Ambil staff di divisi tersebut (Filter Level):
    // - Level 3 
    // - Level 4 ke bawah TANPA Unit (unit_id IS NULL or 0)
    $stmtStaff = $conn->prepare("
        SELECT e.id, e.full_name as name, p.name as position_name
        FROM employees e
        INNER JOIN positions p ON e.position_id = p.id
        WHERE e.division_id = ? AND e.id != ? AND e.status = 'active'
        AND (p.level = 3 OR (p.level >= 4 AND (e.unit_id IS NULL OR e.unit_id = 0)))
        ORDER BY e.full_name ASC
    ");
    $stmtStaff->execute([$divId, $kabid_id]);
    $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $staffList]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
