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

    // 1. Ambil daftar Unit yang berhak diakses oleh user sesuai hirarki berurutan
    $unitsList = [];

    if ($userLevel === 1) {
        // Level 1: Mudir - dapat mengakses seluruh unit di yayasan
        try {
            $stmtUnits = $conn->prepare("
                SELECT DISTINCT u.id, u.name as unit_name
                FROM units u
                INNER JOIN employees e ON e.unit_id = u.id
                WHERE e.status = 'active'
                ORDER BY u.name ASC
            ");
            $stmtUnits->execute();
            $unitsList = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            $unitsList = [];
        }
    } else if ($userLevel === 2) {
        // Level 2: Kepala Bidang (Kabid) - hanya unit di bawah divisinya
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
    } else if ($userLevel === 3) {
        // Level 3: Kepala Unit (Kanit) / Kasub - HANYA unit miliknya dan sub-unit di bawah unit tersebut (jika ada)
        if ($userUnitId) {
            try {
                // Ambil nama unit induk
                $stmtUName = $conn->prepare("SELECT name FROM units WHERE id = ?");
                $stmtUName->execute([$userUnitId]);
                $myUnitName = $stmtUName->fetchColumn() ?: '';

                // Cek apakah ada kolom parent_id di tabel units
                $hasParentCol = false;
                try {
                    $chk = $conn->query("SHOW COLUMNS FROM units LIKE 'parent_id'");
                    $hasParentCol = ($chk && $chk->rowCount() > 0);
                } catch (Exception $e) {}

                if ($hasParentCol) {
                    $stmtUnits = $conn->prepare("
                        SELECT DISTINCT u.id, u.name as unit_name
                        FROM units u
                        WHERE u.id = ? 
                           OR u.parent_id = ? 
                           OR (u.division_id = ? AND u.name LIKE ? AND LOWER(u.name) NOT LIKE '%ma\'had aly%')
                        ORDER BY u.name ASC
                    ");
                    $stmtUnits->execute([
                        $userUnitId,
                        $userUnitId,
                        $userDivId,
                        $myUnitName . ' - %'
                    ]);
                } else {
                    $stmtUnits = $conn->prepare("
                        SELECT DISTINCT u.id, u.name as unit_name
                        FROM units u
                        WHERE u.id = ? 
                           OR (u.division_id = ? AND u.name LIKE ? AND LOWER(u.name) != 'ma\'had aly')
                        ORDER BY u.name ASC
                    ");
                    $stmtUnits->execute([
                        $userUnitId,
                        $userDivId,
                        $myUnitName . ' - %'
                    ]);
                }
                $unitsList = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);

                // Pastikan unit Ma'had (id 16) dan Ma'had Aly (id 15) benar-benar terpisah
                if ((int)$userUnitId === 16 || strtolower($myUnitName) === "ma'had") {
                    $unitsList = array_values(array_filter($unitsList, function($u) {
                        return (int)$u['id'] !== 15 && strtolower($u['unit_name']) !== "ma'had aly";
                    }));
                } else if ((int)$userUnitId === 15 || strtolower($myUnitName) === "ma'had aly") {
                    $unitsList = array_values(array_filter($unitsList, function($u) {
                        return (int)$u['id'] !== 16 && strtolower($u['unit_name']) !== "ma'had";
                    }));
                }
            } catch (Exception $ex) {
                $unitsList = [];
            }
        }
    } else {
        // Level 4/5 atau staf: hanya unit miliknya
        if ($userUnitId) {
            try {
                $stmtUnits = $conn->prepare("SELECT id, name as unit_name FROM units WHERE id = ?");
                $stmtUnits->execute([$userUnitId]);
                $unitsList = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $ex) {
                $unitsList = [];
            }
        }
    }

    $allowedUnitIds = array_map(function($u) { return (int)$u['id']; }, $unitsList);

    $staffList = [];

    // Validasi apakah filter unit spesifik dipilih
    $hasSpecificUnit = ($unit_id && $unit_id !== '0' && $unit_id !== 'all' && strtolower($unit_id) !== 'semua unit');

    if ($hasSpecificUnit) {
        $targetUnitId = null;
        if (is_numeric($unit_id)) {
            $targetUnitId = (int)$unit_id;
        } else {
            foreach ($unitsList as $u) {
                if (strtolower($u['unit_name']) === strtolower($unit_id)) {
                    $targetUnitId = (int)$u['id'];
                    break;
                }
            }
        }

        // Keamanan Hirarki: Jika user level >= 2, pastikan unit yang diminta ada dalam daftar yang diizinkan
        if ($userLevel > 1 && !in_array($targetUnitId, $allowedUnitIds, true)) {
            $targetUnitId = null;
        }

        if ($targetUnitId) {
            $stmtStaff = $conn->prepare("
                SELECT e.id, e.full_name as name, p.name as position_name, u.name as unit_name, e.profile_photo
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                LEFT JOIN units u ON e.unit_id = u.id
                WHERE e.unit_id = ? AND e.id != ? AND e.status = 'active'
                ORDER BY e.full_name ASC
            ");
            $stmtStaff->execute([$targetUnitId, $user_id]);
            $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Jika tidak ada filter unit spesifik (atau unit tidak diizinkan)
    if (!$hasSpecificUnit || empty($staffList)) {
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
            // Kepala Unit/Sub: Tampilkan semua pegawai dalam unitnya (dan sub-unit jika ada)
            if (!empty($allowedUnitIds)) {
                $inClause = implode(',', $allowedUnitIds);
                $stmtStaff = $conn->prepare("
                    SELECT e.id, e.full_name as name, p.name as position_name, u.name as unit_name, e.profile_photo
                    FROM employees e
                    INNER JOIN positions p ON e.position_id = p.id
                    LEFT JOIN units u ON e.unit_id = u.id
                    WHERE e.unit_id IN ($inClause) AND e.id != ? AND e.status = 'active'
                    ORDER BY e.full_name ASC
                ");
                $stmtStaff->execute([$user_id]);
                $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $staffList = [];
            }
        } else {
            // Untuk level lain: Tampilkan staff dalam unit yang sama
            if ($userUnitId) {
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
            } else {
                $staffList = [];
            }
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
