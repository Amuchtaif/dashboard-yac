<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('can_access_kesantrian');

$db = new Database();
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';

if ($action === 'submit_attendance') {
    $room_id = $_POST['room_id'] ?? null;
    $date = $_POST['date'] ?? null;
    $attendance_data = $_POST['attendance'] ?? [];
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$room_id || !$date) {
        header("Location: ../../views/boarding/attendance/index.php?error=Invalid+data");
        exit;
    }

    try {
        $conn->beginTransaction();

        $upsert_sql = "
            INSERT INTO boarding_attendances (room_id, student_id, date, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                notes = VALUES(notes),
                created_by = VALUES(created_by)
        ";
        $stmt = $conn->prepare($upsert_sql);

        foreach ($attendance_data as $student_id => $data) {
            $status = $data['status'] ?? 'Alpha';
            $notes = $data['notes'] ?? '';
            $stmt->execute([$room_id, $student_id, $date, $status, $notes, $user_id]);
        }

        $conn->commit();
        header("Location: ../../views/boarding/attendance/index.php?date=$date&success=Absensi asrama berhasil disimpan");
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: ../../views/boarding/attendance/room_attendance.php?room_id=$room_id&date=$date&error=" . urlencode($e->getMessage()));
        exit;
    }
}

        header("Location: ../../views/boarding/attendance/index.php?success=Operasi+berhasil");
exit;
