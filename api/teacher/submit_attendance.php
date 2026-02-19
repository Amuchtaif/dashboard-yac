<?php
header('Content-Type: application/json');
require '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$schedule_id = $input['schedule_id'] ?? null;
$teacher_id = $input['teacher_id'] ?? null; // Employee ID
$date = $input['date'] ?? date('Y-m-d');
$topic = $input['topic'] ?? '';
$notes = $input['notes'] ?? '';
$attendances = $input['attendances'] ?? []; // Array of {student_id, status, note}

if (!$schedule_id || !$teacher_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing schedule_id or teacher_id']);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Check/Create Journal
    $stmt = $conn->prepare("SELECT id FROM class_journals WHERE class_schedule_id = ? AND date = ?");
    $stmt->execute([$schedule_id, $date]);
    $journal = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($journal) {
        $journal_id = $journal['id'];
        // Update topic/notes if provided
        $update_stmt = $conn->prepare("UPDATE class_journals SET topic = ?, notes = ?, teacher_id = ? WHERE id = ?");
        $update_stmt->execute([$topic, $notes, $teacher_id, $journal_id]);
    } else {
        // Create new
        $insert_stmt = $conn->prepare("INSERT INTO class_journals (class_schedule_id, teacher_id, date, topic, notes, start_time, end_time) VALUES (?, ?, ?, ?, ?, '00:00:00', '00:00:00')");
        // Getting start/end time from schedule for reference? Or actual time?
        // Let's fetch schedule times first to fill start/end meaningfully.
        $sch_stmt = $conn->prepare("SELECT start_time, end_time FROM lesson_periods lp JOIN class_schedules cs ON cs.lesson_period_id = lp.id WHERE cs.id = ?");
        $sch_stmt->execute([$schedule_id]);
        $times = $sch_stmt->fetch(PDO::FETCH_ASSOC);
        $start = $times ? $times['start_time'] : '00:00:00';
        $end = $times ? $times['end_time'] : '00:00:00';

        $insert_stmt->execute([$schedule_id, $teacher_id, $date, $topic, $notes]); // start/end handled by trigger or default? No, I added them in PHP. Wait, I didn't verify table structure for start/end.
        // I created table with start_time, end_time.
        // Re-check create_attendance_tables.php:
        // class_journals ... start_time TIME NOT NULL, end_time TIME NOT NULL ...
        // So I must provide them.
        
        $insert_stmt = $conn->prepare("INSERT INTO class_journals (class_schedule_id, teacher_id, date, topic, notes, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->execute([$schedule_id, $teacher_id, $date, $topic, $notes, $start, $end]);
        $journal_id = $conn->lastInsertId();
    }

    // 2. Process Attendances
    $att_insert = $conn->prepare("INSERT INTO student_attendances (class_journal_id, student_id, status, note) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)");
    
    // Check if table has UNIQUE KEY on (class_journal_id, student_id) for ON DUPLICATE KEY UPDATE to work.
    // I defined primary key ID, foreign keys, but technically didn't enforce composite unique key.
    // I should create it or check first.
    // To be safe, I'll delete existing for this journal+student or just select first.
    // Efficient way: DELETE FROM student_attendances WHERE class_journal_id = ? AND student_id IN (...) then INSERT.
    // Or just check if exists.
    
    // I'll assume we can loop and do check-update/insert.
    foreach ($attendances as $att) {
        $sid = $att['student_id'];
        $status = $att['status']; // 'present', 'absent', etc.
        $note = $att['note'] ?? '';

        // Check exists
        $check = $conn->prepare("SELECT id FROM student_attendances WHERE class_journal_id = ? AND student_id = ?");
        $check->execute([$journal_id, $sid]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $upd = $conn->prepare("UPDATE student_attendances SET status = ?, note = ? WHERE id = ?");
            $upd->execute([$status, $note, $existing['id']]);
        } else {
            $ins = $conn->prepare("INSERT INTO student_attendances (class_journal_id, student_id, status, note) VALUES (?, ?, ?, ?)");
            $ins->execute([$journal_id, $sid, $status, $note]);
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Attendance saved', 'journal_id' => $journal_id]);

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
