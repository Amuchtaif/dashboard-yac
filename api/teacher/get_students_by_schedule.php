<?php
header('Content-Type: application/json');
require '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $schedule_id = $_GET['schedule_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');

    if (!$schedule_id) {
        throw new Exception('Missing schedule_id parameter');
    }

    // 1. Get Schedule details from class_schedules
    $stmt = $conn->prepare("
        SELECT 
            cs.id, cs.grade_level_id, cs.subject_id, cs.employee_id, cs.day,
            gl.name as class_name,
            s.name as subject_name,
            lp.start_time, lp.end_time
        FROM class_schedules cs
        JOIN grade_levels gl ON cs.grade_level_id = gl.id
        JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
        WHERE cs.id = ?
    ");
    $stmt->execute([$schedule_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        throw new Exception('Schedule not found');
    }

    $class_name = $schedule['class_name'];

    // 2. Get Journal (if exists) - uses class_schedule_id
    $j_stmt = $conn->prepare("SELECT id, topic, notes FROM class_journals WHERE class_schedule_id = ? AND date = ?");
    $j_stmt->execute([$schedule_id, $date]);
    $journal = $j_stmt->fetch(PDO::FETCH_ASSOC);
    $journal_id = $journal ? $journal['id'] : 0;

    // 3. Get Students by matching students.kelas = grade_levels.name (class_name)
    $s_stmt = $conn->prepare("
        SELECT 
            st.id as student_id, 
            st.nama_siswa as student_name,
            sa.status
        FROM students st 
        LEFT JOIN student_attendances sa 
            ON st.id = sa.student_id AND sa.class_journal_id = ?
        WHERE st.kelas = ?
        ORDER BY st.nama_siswa ASC
    ");
    $s_stmt->execute([$journal_id, $class_name]);
    $students = $s_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fix null status
    foreach ($students as &$s) {
        if ($s['status'] === null) {
            $s['status'] = '';
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'schedule' => $schedule,
            'journal' => $journal,
            'students' => $students
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
