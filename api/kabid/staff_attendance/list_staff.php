<?php
// api/kabid/staff_attendance/list_staff.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
require_once dirname(__DIR__, 3) . '/config/database.php';

try {
    /** @var \Database $db */
    $db = new Database();
    $conn = $db->getConnection();

    $user_id = $_GET['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(["success" => false, "message" => "Parameter user_id wajib diisi."]);
        exit;
    }

    // Ambil info user (Level, Divisi, & Unit)
    $stmtUser = $conn->prepare("
        SELECT e.division_id, e.unit_id, p.level 
        FROM employees e 
        INNER JOIN positions p ON e.position_id = p.id 
        WHERE e.id = ? AND e.status = 'active'
    ");
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "User tidak ditemukan."]);
        exit;
    }

    $userLevel = (int)$user['level'];
    $userDivId = $user['division_id'];
    $userUnitId = $user['unit_id'];

    $staffList = [];

    if ($userLevel === 1) {
        // Mudir (Muksin): Tampilkan semua Kepala Bidang (Level 2)
        $stmtStaff = $conn->prepare("
            SELECT e.id, e.full_name as name, p.name as position_name
            FROM employees e
            INNER JOIN positions p ON e.position_id = p.id
            WHERE p.level = 2 AND e.status = 'active'
            ORDER BY e.full_name ASC
        ");
        $stmtStaff->execute();
        $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
    } else if ($userLevel === 2) {
        // Kepala Bidang (Kabid): Tampilkan Kepala Unit/Sub (Level 3) 
        // dan Staff Langsung di bawah divisi (Posisi 'Staf' dengan unit_id kosong)
        $stmtStaff = $conn->prepare("
            SELECT e.id, e.full_name as name, p.name as position_name
            FROM employees e
            INNER JOIN positions p ON e.position_id = p.id
            WHERE e.division_id = ? 
              AND e.id != ? 
              AND e.status = 'active'
              AND (
                  p.level = 3 
                  OR (p.name = 'Staf' AND (e.unit_id IS NULL OR e.unit_id = 0))
              )
            ORDER BY e.full_name ASC
        ");
        $stmtStaff->execute([$userDivId, $user_id]);
        $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
    } else if ($userLevel === 3) {
        // Kepala Unit/Sub: Tampilkan semua pegawai dalam satu unit
        if (!$userUnitId) {
            $staffList = [];
        } else {
            $stmtStaff = $conn->prepare("
                SELECT e.id, e.full_name as name, p.name as position_name
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                WHERE e.unit_id = ? AND e.id != ? AND e.status = 'active'
                ORDER BY e.full_name ASC
            ");
            $stmtStaff->execute([$userUnitId, $user_id]);
            $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Untuk level lain (opsional): Tampilkan staff dalam divisi yang sama
        $stmtStaff = $conn->prepare("
            SELECT e.id, e.full_name as name, p.name as position_name
            FROM employees e
            INNER JOIN positions p ON e.position_id = p.id
            WHERE e.division_id = ? AND e.id != ? AND e.status = 'active'
            ORDER BY e.full_name ASC
        ");
        $stmtStaff->execute([$userDivId, $user_id]);
        $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(["success" => true, "data" => $staffList]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
