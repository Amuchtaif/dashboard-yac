<?php
header('Content-Type: application/json');
require '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$employee_id = $_GET['employee_id'] ?? null;
$day = $_GET['day'] ?? date('l'); // 'Monday', 'Tuesday', ...

if (!$employee_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing employee_id"]);
    exit;
}

// Map Indonesian days request if needed, but DB uses English usually unless configured
// Based on previous work, DB stores 'Monday', 'Tuesday' etc.

try {
    $sql = "
        SELECT 
            cs.id, 
            s.name as subject_name,
            gl.name as class_name,
            lp.start_time,
            lp.end_time,
            cs.day,
            (SELECT COUNT(*) FROM class_journals cj WHERE cj.class_schedule_id = cs.id AND cj.date = CURDATE()) as is_attended
        FROM class_schedules cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN grade_levels gl ON cs.grade_level_id = gl.id
        JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
        WHERE cs.employee_id = :employee_id AND cs.day = :day
        ORDER BY lp.start_time ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':employee_id' => $employee_id, ':day' => $day]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $schedules
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
