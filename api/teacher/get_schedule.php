<?php
header('Content-Type: application/json');
require '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$employee_id = $_GET['employee_id'] ?? null;
$day = $_GET['day'] ?? date('l'); // 'Monday', 'Tuesday', ...
$date = $_GET['date'] ?? date('Y-m-d');

if (!$employee_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing employee_id"]);
    exit;
}

// Map Indonesian days request if needed, but DB uses English usually unless configured
// Based on previous work, DB stores 'Monday', 'Tuesday' etc.
$day_map = [
    'Senin' => 'Monday',
    'Selasa' => 'Tuesday',
    'Rabu' => 'Wednesday',
    'Kamis' => 'Thursday',
    'Jumat' => 'Friday',
    'Sabtu' => 'Saturday',
    'Ahad' => 'Sunday',
    'Minggu' => 'Sunday'
];

if (isset($day_map[$day])) {
    $day = $day_map[$day];
}

try {
    // Fetch Active Academic Year
    $active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    if (!$active_year_id) {
        $active_year_id = 1;
    }

    $sql = "
        SELECT 
            cs.id, 
            s.name as subject_name,
            gl.name as class_name,
            lp.start_time,
            COALESCE(lp_end.end_time, lp.end_time) as end_time,
            cs.day,
            (SELECT COUNT(*) FROM class_journals cj WHERE cj.class_schedule_id = cs.id AND cj.date = :date AND (cj.topic != '' AND cj.notes != '')) as is_journal_filled,
            (SELECT COUNT(*) FROM student_attendances sa 
             JOIN class_journals cj ON sa.class_journal_id = cj.id 
             WHERE cj.class_schedule_id = cs.id AND cj.date = :date) as has_attendance
        FROM class_schedules cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN grade_levels gl ON cs.grade_level_id = gl.id
        LEFT JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
        LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
        WHERE cs.employee_id = :employee_id AND cs.day = :day AND cs.academic_year_id = :active_year_id
        ORDER BY lp.start_time ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':employee_id' => $employee_id,
        ':day' => $day,
        ':active_year_id' => $active_year_id,
        ':date' => $date
    ]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $schedules
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
