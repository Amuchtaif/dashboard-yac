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
    $unit_id = $_GET['unit_id'] ?? $_GET['unit'] ?? null;

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

    // Ambil semua daftar Unit di bawah divisi/departemen user yang sedang login
    $unitsList = [];
    if ($userDivId) {
        try {
            $stmtUnits = $conn->prepare("
                SELECT DISTINCT u.id, u.name as unit_name
                FROM units u
                INNER JOIN employees e ON e.unit_id = u.id
                WHERE e.division_id = ? AND e.status = 'active'
                ORDER BY u.name ASC
            ");
            $stmtUnits->execute([$userDivId]);
            $unitsList = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            $unitsList = [];
        }
    }

    $staffList = [];

    // Jika filter unit_id / unit_name spesifik dipilih (dan bukan '0' atau 'all' atau 'Semua Unit')
    if ($unit_id && $unit_id !== '0' && $unit_id !== 'all' && strtolower($unit_id) !== 'semua unit') {
        if (is_numeric($unit_id)) {
            $stmtStaff = $conn->prepare("
                SELECT e.id, e.full_name as name, p.name as position_name, u.name as unit_name, e.profile_photo
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE e.unit_id = ? AND e.id != ? AND e.status = 'active'
                ORDER BY e.full_name ASC
            ");
            $stmtStaff->execute([$unit_id, $user_id]);
        } else {
            $stmtStaff = $conn->prepare("
                SELECT e.id, e.full_name as name, p.name as position_name, u.name as unit_name, e.profile_photo
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE LOWER(u.name) = LOWER(?) AND e.id != ? AND e.status = 'active'
                ORDER BY e.full_name ASC
            ");
            $stmtStaff->execute([$unit_id, $user_id]);
        }
        $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // TAMPILAN DEFAULT AWAL (SESUAI ATURAN SEMULA)
        if ($userLevel === 1) {
            // Mudir (Muksin): Tampilkan semua Kepala Bidang (Level 2)
            $stmtStaff = $conn->prepare("
                SELECT e.id, e.full_name as name, p.name as position_name, u.name as unit_name, e.profile_photo
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE p.level = 2 AND e.status = 'active'
                ORDER BY e.full_name ASC
            ");
            $stmtStaff->execute();
            $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
        } else if ($userLevel === 2) {
            // Kepala Bidang (Kabid): Tampilkan Kepala Unit/Sub (Level 3) 
            // dan Staff Langsung di bawah divisi (Posisi 'Staf' dengan unit_id kosong)
            $stmtStaff = $conn->prepare("
                SELECT e.id, e.full_name as name, p.name as position_name, u.name as unit_name, e.profile_photo
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                LEFT JOIN units u ON e.unit_id = u.id
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
                    SELECT e.id, e.full_name as name, p.name as position_name, u.name as unit_name, e.profile_photo
                    FROM employees e
                    INNER JOIN positions p ON e.position_id = p.id
                    LEFT JOIN units u ON e.unit_id = u.id
                    WHERE e.unit_id = ? AND e.id != ? AND e.status = 'active'
                    ORDER BY e.full_name ASC
                ");
                $stmtStaff->execute([$userUnitId, $user_id]);
                $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Untuk level lain: Tampilkan staff dalam divisi yang sama
            $stmtStaff = $conn->prepare("
                SELECT e.id, e.full_name as name, p.name as position_name, u.name as unit_name, e.profile_photo
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE e.division_id = ? AND e.id != ? AND e.status = 'active'
                ORDER BY e.full_name ASC
            ");
            $stmtStaff->execute([$userDivId, $user_id]);
            $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        "success" => true,
        "data" => $staffList,
        "units" => $unitsList
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
