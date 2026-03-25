<?php
// api/kabid/staff_attendance/get.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $kabid_id = $_GET['user_id'] ?? null;
    $target_date = $_GET['date'] ?? date('Y-m-d');

    if (!$kabid_id) {
        echo json_encode(["success" => false, "message" => "Parameter user_id (Kabid ID) wajib diisi."]);
        exit;
    }

    // 1. Ambil Divisi Kabid
    $stmtKabid = $conn->prepare("SELECT division_id FROM employees WHERE id = ?");
    $stmtKabid->execute([$kabid_id]);
    $kabid = $stmtKabid->fetch(PDO::FETCH_ASSOC);

    if (!$kabid || !$kabid['division_id']) {
        echo json_encode(["success" => false, "message" => "User bukan Kabid atau tidak memiliki divisi."]);
        exit;
    }

    $division_id = $kabid['division_id'];

    // 2. Ambil List Staff di Divisi Tersebut (Kecuali Kabid Sendiri)
    // Kita Join dengan Attendance dan Permits untuk tanggal tersebut
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
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN attendances a ON e.id = a.user_id AND a.date = :target_date
        WHERE e.division_id = :division_id 
        AND e.id != :kabid_id
        AND e.status = 'active'
        ORDER BY e.full_name ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':target_date', $target_date);
    $stmt->bindParam(':division_id', $division_id);
    $stmt->bindParam(':kabid_id', $kabid_id);
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
