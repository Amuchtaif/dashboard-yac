<?php
// api/meal_attendance/get_students_by_wali_kelas.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $wali_kelas_id = $_GET['wali_kelas_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    $meal_type = $_GET['meal_type'] ?? 'Siang';

    if (!$wali_kelas_id) {
        echo json_encode(["success" => false, "message" => "wali_kelas_id parameter is required."]);
        exit();
    }

    // 1. Detect which class is supervised by this wali_kelas_id
    $class_stmt = $conn->prepare("
        SELECT id, name as class_name 
        FROM grade_levels 
        WHERE teacher_id = ?
        LIMIT 1
    ");
    $class_stmt->execute([$wali_kelas_id]);
    $classInfo = $class_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$classInfo) {
        echo json_encode(["success" => false, "message" => "Anda belum ditetapkan sebagai wali kelas untuk kelas manapun."]);
        exit();
    }

    // Fetch Active Academic Year
    $active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    if (!$active_year_id) {
        $active_year_id = 1;
    }

    // 1b. Check if this class has already been filled by anyone for this date and meal_type
    $check_filled_stmt = $conn->prepare("
        SELECT ma.created_by, (SELECT full_name FROM employees WHERE id = ma.created_by) as creator_name
        FROM meal_attendances ma
        JOIN student_class_history sch ON ma.student_id = sch.student_id
        WHERE sch.class_id = ? AND sch.academic_year_id = ? AND sch.status = 'ACTIVE' AND ma.date = ? AND ma.meal_type = ?
        LIMIT 1
    ");
    $check_filled_stmt->execute([$classInfo['id'], $active_year_id, $date, $meal_type]);
    $filled_res = $check_filled_stmt->fetch(PDO::FETCH_ASSOC);
    
    $is_locked = (bool)$filled_res;
    $filled_by_name = $filled_res['creator_name'] ?? null;
    $filled_by_id = $filled_res['created_by'] ?? null;

    $lock_status = ($is_locked && $filled_by_id != $wali_kelas_id);

    // 2. Get students in this class and their meal attendance status
    $sql = "
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk, 
            gl.name as kelas,
            ma.id as attendance_id,
            ma.check_time
        FROM students s
        JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
        JOIN grade_levels gl ON sch.class_id = gl.id
        LEFT JOIN meal_attendances ma ON s.id = ma.student_id 
            AND ma.meal_type = :meal_type 
            AND ma.date = :date
        WHERE sch.class_id = :class_id AND s.status = 'Aktif'
        ORDER BY s.nama_siswa ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':class_id' => $classInfo['id'],
        ':active_year_id' => $active_year_id,
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
            "room_name" => "Kelas " . $classInfo['class_name'],
            "attendance_id" => $row['attendance_id'],
            "check_time" => $row['check_time'],
            "status" => ($row['attendance_id'] ? "Sudah" : "Belum")
        ];
    }

    echo json_encode([
        "success" => true, 
        "room_info" => ["id" => $classInfo['id'], "room_name" => "Kelas " . $classInfo['class_name']],
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
