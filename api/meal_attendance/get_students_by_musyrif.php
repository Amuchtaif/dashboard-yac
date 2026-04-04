<?php
// api/meal_attendance/get_students_by_musyrif.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $musyrif_id = $_GET['musyrif_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    $meal_type = $_GET['meal_type'] ?? 'Siang';

    if (!$musyrif_id) {
        echo json_encode(["success" => false, "message" => "musyrif_id parameter is required."]);
        exit();
    }

    // 1. Detect which rooms are supervised by this musyrif_id (Mapping table or Legacy column)
    $room_stmt = $conn->prepare("
        SELECT br.id, br.room_name 
        FROM boarding_rooms br
        LEFT JOIN boarding_room_supervisors brs ON br.id = brs.room_id
        WHERE brs.supervisor_id = ? OR br.supervisor_id = ?
        LIMIT 1
    ");
    $room_stmt->execute([$musyrif_id, $musyrif_id]);
    $room = $room_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        echo json_encode(["success" => false, "message" => "Musyrif belum ditetapkan ke asrama manapun."]);
        exit();
    }

    // 1b. Check if this room has already been filled by anyone for this date and meal_type
    $check_filled_stmt = $conn->prepare("
        SELECT ma.created_by, (SELECT full_name FROM employees WHERE id = ma.created_by) as creator_name
        FROM meal_attendances ma
        JOIN boarding_room_members brm ON ma.student_id = brm.student_id
        WHERE brm.room_id = ? AND ma.date = ? AND ma.meal_type = ?
        LIMIT 1
    ");
    $check_filled_stmt->execute([$room['id'], $date, $meal_type]);
    $filled_res = $check_filled_stmt->fetch(PDO::FETCH_ASSOC);
    
    $is_locked = (bool)$filled_res;
    $filled_by_name = $filled_res['creator_name'] ?? null;
    $filled_by_id = $filled_res['created_by'] ?? null;

    // Additional: If filled by same musyrif, maybe don't lock it?
    // User requested: "batasi jika asrama sudah diabsen oleh musrif 1 musrif yg lain di asrama itu tidak boleh menginput absen lgi"
    // This implies that if Musyrif 1 already did it, Musyrif 2 is blocked.
    // So if current musyrif is NOT the creator, lock it. 
    // Wait, if it IS the creator, they might want to EDIT. 
    // Let's use the USER's wording: "tidak boleh menginput absen lgi cukup tampilkan data absen yg sudah dilakukan musrif 1"
    // This sounds like even Musyrif 1 shouldn't input again once saved.
    // But normally we allow editing if it's the same person.
    
    // For now, let's keep is_locked as true if ANY creator exists and it's not the current musyrif.
    if ($is_locked && $filled_by_id != $musyrif_id) {
        $lock_status = true;
    } else {
        $lock_status = false; 
    }
    // Actually, let's just use the boolean is_locked for now as a general flag.

    // 2. Get students in this room and their meal attendance status
    $sql = "
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk, 
            s.kelas,
            ma.id as attendance_id,
            ma.check_time
        FROM students s
        JOIN boarding_room_members brm ON s.id = brm.student_id
        LEFT JOIN meal_attendances ma ON s.id = ma.student_id 
            AND ma.meal_type = :meal_type 
            AND ma.date = :date
        WHERE brm.room_id = :room_id
        ORDER BY s.nama_siswa ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':room_id' => $room['id'],
        ':meal_type' => $meal_type,
        ':date' => $date
    ]);
    
    $students_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $students = [];

    foreach ($students_raw as $row) {
        $students[] = [
            "id" => $row['id'],
            "nama_siswa" => $row['nama_siswa'],
            "nomor_induk" => $row['nomor_induk'],
            "kelas" => $row['kelas'],
            "room_name" => $room['room_name'],
            "attendance_id" => $row['attendance_id'],
            "check_time" => $row['check_time'],
            "status" => ($row['attendance_id'] ? "Sudah" : "Belum")
        ];
    }

    echo json_encode([
        "success" => true, 
        "room_info" => $room,
        "date" => $date,
        "meal_type" => $meal_type,
        "is_locked" => $lock_status,
        "filled_by_name" => $filled_by_name,
        "data" => $students
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
