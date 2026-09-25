<?php
// api/kabid/staff_attendance/get.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once dirname(__DIR__, 3) . '/config/database.php';

try {
    /** @var \Database $db */
    $db = new Database();
    $conn = $db->getConnection();

    $user_id = $_GET['user_id'] ?? null;
    $target_date = $_GET['date'] ?? date('Y-m-d');
    $unit_id = $_GET['unit_id'] ?? $_GET['unit'] ?? null;

    if (!$user_id) {
        echo json_encode(["success" => false, "message" => "Parameter user_id wajib diisi."]);
        exit;
    }

    // 1. Ambil info user (Level, Divisi, & Unit)
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
    $division_id = $user['division_id'];
    $user_unit_id = $user['unit_id'];

    // 2. Ambil daftar unit yang diizinkan sesuai hirarki
    $allowedUnitIds = [];
    if ($userLevel === 1) {
        // Mudir: semua unit
        $stmtUnits = $conn->query("SELECT id FROM units");
        $allowedUnitIds = $stmtUnits->fetchAll(PDO::FETCH_COLUMN);
    } else if ($userLevel === 2) {
        // Kabid: unit di bawah divisinya
        $stmtUnits = $conn->prepare("SELECT id FROM units WHERE division_id = ?");
        $stmtUnits->execute([$division_id]);
        $allowedUnitIds = $stmtUnits->fetchAll(PDO::FETCH_COLUMN);
    } else if ($userLevel === 3) {
        // Kepala Unit: unit miliknya & sub-unit jika ada
        if ($user_unit_id) {
            $stmtUName = $conn->prepare("SELECT name FROM units WHERE id = ?");
            $stmtUName->execute([$user_unit_id]);
            $myUnitName = $stmtUName->fetchColumn() ?: '';

            $hasParentCol = false;
            try {
                $chk = $conn->query("SHOW COLUMNS FROM units LIKE 'parent_id'");
                $hasParentCol = ($chk && $chk->rowCount() > 0);
            } catch (Exception $e) {}

            if ($hasParentCol) {
                $stmtUnits = $conn->prepare("
                    SELECT id FROM units 
                    WHERE id = ? 
                       OR parent_id = ? 
                       OR (division_id = ? AND name LIKE ? AND LOWER(name) NOT LIKE '%ma\'had aly%')
                ");
                $stmtUnits->execute([$user_unit_id, $user_unit_id, $division_id, $myUnitName . ' - %']);
            } else {
                $stmtUnits = $conn->prepare("
                    SELECT id FROM units 
                    WHERE id = ? 
                       OR (division_id = ? AND name LIKE ? AND LOWER(name) != 'ma\'had aly')
                ");
                $stmtUnits->execute([$user_unit_id, $division_id, $myUnitName . ' - %']);
            }
            $allowedUnitIds = $stmtUnits->fetchAll(PDO::FETCH_COLUMN);

            // Pastikan unit Ma'had (id 16) dan Ma'had Aly (id 15) benar-benar terpisah
            if ((int)$user_unit_id === 16 || strtolower($myUnitName) === "ma'had") {
                $allowedUnitIds = array_values(array_filter($allowedUnitIds, function($id) {
                    return (int)$id !== 15;
                }));
            } else if ((int)$user_unit_id === 15 || strtolower($myUnitName) === "ma'had aly") {
                $allowedUnitIds = array_values(array_filter($allowedUnitIds, function($id) {
                    return (int)$id !== 16;
                }));
            }
        }
    } else {
        if ($user_unit_id) $allowedUnitIds = [$user_unit_id];
    }

    $allowedUnitIds = array_map('intval', $allowedUnitIds);

    // 3. Tentukan Filter Subordinat berdasarkan Level / Unit Filter
    $subordinateFilter = "";
    $params = [':target_date' => $target_date, ':user_id' => $user_id];

    $hasSpecificUnit = ($unit_id && $unit_id !== '0' && $unit_id !== 'all' && strtolower($unit_id) !== 'semua unit');

    if ($hasSpecificUnit) {
        $targetUnitId = null;
        if (is_numeric($unit_id)) {
            $targetUnitId = (int)$unit_id;
        } else {
            $stmtFindUnit = $conn->prepare("SELECT id FROM units WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $stmtFindUnit->execute([$unit_id]);
            $foundId = $stmtFindUnit->fetchColumn();
            if ($foundId) $targetUnitId = (int)$foundId;
        }

        // Keamanan Hirarki: pastikan unit yang diminta ada dalam unit yang diizinkan
        if ($userLevel > 1 && !in_array($targetUnitId, $allowedUnitIds, true)) {
            $targetUnitId = null; // Di luar wewenang
        }

        if ($targetUnitId) {
            $subordinateFilter = "e.unit_id = :target_unit_id AND e.id != :user_id";
            $params[':target_unit_id'] = $targetUnitId;
        }
    }

    // Default jika tidak ada filter unit spesifik atau unit tidak diizinkan
    if (empty($subordinateFilter)) {
        if ($userLevel === 1) {
            // Mudir: Tampilkan semua Kepala Bidang (Level 2)
            $subordinateFilter = "p.level = 2";
        } else if ($userLevel === 2) {
            // Kepala Bidang: Tampilkan Kepala Unit/Sub (Level 3) & staf langsung divisi
            $subordinateFilter = "e.division_id = :division_id 
                                 AND e.id != :user_id
                                 AND (
                                     p.level = 3 
                                     OR (p.name = 'Staf' AND (e.unit_id IS NULL OR e.unit_id = 0))
                                 )";
            $params[':division_id'] = $division_id;
        } else if ($userLevel === 3) {
            // Kepala Unit: Tampilkan semua pegawai dalam unitnya (dan sub-unit jika ada)
            if (!empty($allowedUnitIds)) {
                $inClause = implode(',', $allowedUnitIds);
                $subordinateFilter = "e.unit_id IN ($inClause) AND e.id != :user_id";
            } else {
                $subordinateFilter = "1=0";
            }
        } else {
            // Staf/Guru: staff dalam unit yang sama
            if ($user_unit_id) {
                $subordinateFilter = "e.unit_id = :user_unit_id AND e.id != :user_id";
                $params[':user_unit_id'] = $user_unit_id;
            } else {
                $subordinateFilter = "1=0";
            }
        }
    }

    // 4. Ambil List Staff Sesuai Filter
    $query = "
        SELECT 
            e.id, 
            e.full_name, 
            e.profile_photo,
            p.name as position_name,
            u.name as unit_name,
            a.time_in,
            a.status as attendance_status,
            a.status_out as attendance_status_out,
            (SELECT permit_type FROM permits 
             WHERE employee_id = e.id 
             AND status = 'approved' 
             AND :target_date BETWEEN start_date AND end_date 
             LIMIT 1) as permit_type
        FROM employees e
        INNER JOIN positions p ON e.position_id = p.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN attendances a ON e.id = a.user_id AND a.date = :target_date
        WHERE e.status = 'active' AND $subordinateFilter
        ORDER BY e.full_name ASC
    ";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    
    $staffAttendance = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $displayStatus = "Alpha";
        $displayTime = "-";

        if ($row['time_in']) {
            $displayStatus = ($row['attendance_status'] == 'Telat') ? "Terlambat" : "Hadir";
            $displayTime = date('H:i', strtotime($row['time_in']));
        } elseif ($row['permit_type']) {
            $displayStatus = $row['permit_type'];
            $displayTime = "-";
        }

        $staffAttendance[] = [
            "id" => $row['id'],
            "name" => $row['full_name'],
            "position" => $row['position_name'] ?? "-",
            "unit_name" => $row['unit_name'] ?? "",
            "unit" => $row['unit_name'] ?? "",
            "photo" => $row['profile_photo'],
            "time" => $displayTime,
            "status" => $displayStatus
        ];
    }

    echo json_encode([
        "success" => true,
        "date" => $target_date,
        "data" => $staffAttendance
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
