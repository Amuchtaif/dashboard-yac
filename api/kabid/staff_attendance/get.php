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
    $unit_id = $user['unit_id'];

    // 2. Tentukan Filter Subordinat berdasarkan Level
    $subordinateFilter = "";
    if ($userLevel === 1) {
        // Mudir: Tampilkan semua Kepala Bidang (Level 2)
        $subordinateFilter = "p.level = 2";
    } else if ($userLevel === 2) {
        // Kepala Bidang (Kabid): Tampilkan Kepala Unit/Sub (Level 3) 
        // dan Staff Langsung di bawah divisi (Posisi 'Staf' dengan unit_id kosong)
        $subordinateFilter = "e.division_id = :division_id 
                             AND e.id != :user_id
                             AND (
                                 p.level = 3 
                                 OR (p.name = 'Staf' AND (e.unit_id IS NULL OR e.unit_id = 0))
                             )";
    } else if ($userLevel === 3) {
        // Kepala Unit/Sub: Tampilkan semua pegawai dalam satu unit
        $subordinateFilter = "e.unit_id = :unit_id AND e.id != :user_id";
    } else {
        // Level lain: Sembunyikan atau sesuaikan
        $subordinateFilter = "1=0";
    }

    // 3. Ambil List Staff Sesuai Filter
    $query = "
        SELECT 
            e.id, 
            e.full_name, 
            e.profile_photo,
            p.name as position_name,
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
        LEFT JOIN attendances a ON e.id = a.user_id AND a.date = :target_date
        WHERE e.status = 'active' AND $subordinateFilter
        ORDER BY e.full_name ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':target_date', $target_date);
    if ($userLevel === 2) {
        $stmt->bindParam(':division_id', $division_id);
        $stmt->bindParam(':user_id', $user_id);
    } else if ($userLevel === 3) {
        $stmt->bindParam(':unit_id', $unit_id);
        $stmt->bindParam(':user_id', $user_id);
    }
    $stmt->execute();
    
    $staffAttendance = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Tentukan Final Status untuk UI Flutter
        $displayStatus = "Alpha";
        $displayTime = "-";

        if ($row['time_in']) {
            $displayStatus = ($row['attendance_status'] == 'Telat') ? "Terlambat" : "Hadir";
            $displayTime = date('H:i', strtotime($row['time_in']));
        } elseif ($row['permit_type']) {
            $displayStatus = $row['permit_type']; // Sakit, Izin, Cuti, dll
            $displayTime = "-";
        }

        $staffAttendance[] = [
            "id" => $row['id'],
            "name" => $row['full_name'],
            "position" => $row['position_name'] ?? "-",
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
