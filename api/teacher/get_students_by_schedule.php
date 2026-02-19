<?php
header('Content-Type: application/json');
require '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$schedule_id = $_GET['schedule_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d'); // Current date if not provided

if (!$schedule_id) {
    echo json_encode(['status' => 'error', 'message' => 'Schedule ID missing']);
    exit;
}

try {
    // 1. Get Class Info
    $stmt = $conn->prepare("SELECT gl.name as class_name, s.name as subject_name FROM class_schedules cs JOIN grade_levels gl ON cs.grade_level_id = gl.id JOIN subjects s ON cs.subject_id = s.id WHERE cs.id = ?");
    $stmt->execute([$schedule_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        echo json_encode(['status' => 'error', 'message' => 'Schedule not found']);
        exit;
    }

    $class_name = $schedule['class_name'];

    // 2. Check if Journal exists for this date
    $journal_stmt = $conn->prepare("SELECT id, topic, notes FROM class_journals WHERE class_schedule_id = ? AND date = ?");
    $journal_stmt->execute([$schedule_id, $date]);
    $journal = $journal_stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Get Students and their attendance status (if taken)
    // Left Join with student_attendances via class_journal_id if exists
    $sql = "
        SELECT 
            st.id as student_id, 
            st.nama_siswa as student_name,
            COALESCE(sa.status, 'present') as status,
            COALESCE(sa.note, '') as note
        FROM students st
        LEFT JOIN student_attendances sa ON st.id = sa.student_id AND sa.class_journal_id = :journal_id
        WHERE st.kelas = :class_name
        ORDER BY st.nama_siswa ASC
    ";

    $journal_id = $journal ? $journal['id'] : 0; // 0 ensures no match if journal not exists, but we use :journal_id param

    $stud_stmt = $conn->prepare($sql);
    $stud_stmt->execute([':class_name' => $class_name, ':journal_id' => $journal_id]);
    $students = $stud_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'schedule' => $schedule,
            'journal' => $journal,
            'students' => $students
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
