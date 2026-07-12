<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['class_id'])) {
    echo json_encode(["success" => false, "message" => "Class ID required"]);
    exit;
}

$class_id = $_GET['class_id'];
$today = date('l'); // Current day, e.g., 'Monday'

try {
    // 1. Get Class Info
    $queryClass = "SELECT 
                    gl.id, 
                    gl.name as class_name, 
                    gl.category as unit_name,
                    e.full_name as teacher_name,
                    gl.capacity
                  FROM grade_levels gl
                  LEFT JOIN employees e ON gl.teacher_id = e.id
                  WHERE gl.id = :id";
    $stmtClass = $db->prepare($queryClass);
    $stmtClass->bindParam(':id', $class_id);
    $stmtClass->execute();
    $classInfo = $stmtClass->fetch(PDO::FETCH_ASSOC);

    if (!$classInfo) {
        echo json_encode(["success" => false, "message" => "Class not found"]);
        exit;
    }

    // 2. Get Today's Schedule
    $querySchedule = "SELECT 
                        s.name as subject_name,
                        lp.start_time,
                        COALESCE(lp_end.end_time, lp.end_time) as end_time,
                        e.full_name as teacher_name
                      FROM class_schedules cs
                      JOIN subjects s ON cs.subject_id = s.id
                      JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
                      LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
                      LEFT JOIN employees e ON cs.employee_id = e.id
                      WHERE cs.grade_level_id = :id AND cs.day = :day
                      ORDER BY lp.start_time ASC";
    $stmtSched = $db->prepare($querySchedule);
    $stmtSched->bindParam(':id', $class_id);
    $stmtSched->bindParam(':day', $today);
    $stmtSched->execute();
    $schedules = $stmtSched->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get Student List
    $active_year_id = $db->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    if (!$active_year_id) {
        $active_year_id = 1;
    }

    $queryStudents = "SELECT 
                        s.id, 
                        s.nama_siswa, 
                        s.nomor_induk as nisn,
                        s.foto
                      FROM students s
                      JOIN student_class_history sch ON s.id = sch.student_id
                      WHERE sch.class_id = :class_id 
                        AND sch.academic_year_id = :academic_year_id
                        AND sch.status = 'ACTIVE'
                        AND s.status = 'Aktif'
                      ORDER BY s.nama_siswa ASC";
    $stmtStudents = $db->prepare($queryStudents);
    $stmtStudents->bindParam(':class_id', $class_id, PDO::PARAM_INT);
    $stmtStudents->bindParam(':academic_year_id', $active_year_id, PDO::PARAM_INT);
    $stmtStudents->execute();
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => [
            "info" => [
                "id" => (int)$classInfo['id'],
                "class_name" => $classInfo['class_name'],
                "unit_name" => $classInfo['unit_name'],
                "teacher_name" => $classInfo['teacher_name'] ?? 'Belum Ditentukan',
                "student_count" => count($students),
                "room" => "Ruang " . $classInfo['id']
            ],
            "schedule_today" => array_map(function($s) {
                return [
                    "subject" => $s['subject_name'],
                    "time" => date("H:i", strtotime($s['start_time'])) . " - " . date("H:i", strtotime($s['end_time'])),
                    "teacher" => $s['teacher_name']
                ];
            }, $schedules),
            "students" => array_map(function($st) {
                return [
                    "id" => (int)$st['id'],
                    "name" => $st['nama_siswa'],
                    "nisn" => $st['nisn'],
                    "photo" => $st['foto']
                ];
            }, $students)
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
