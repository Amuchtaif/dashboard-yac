<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $grade_id = $_POST['grade_level_id'] ?? 0;
    $date = $_POST['date'] ?? date('Y-m-d');
    $user_id = $_SESSION['user_id'];
    $att_data = $_POST['att'] ?? [];

    // Check if user is Administrator or has academic management permissions
    if (!isset($_SESSION['position_name'])) {
        $stmt_p = $conn->prepare("SELECT p.name FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
        $stmt_p->execute([$user_id]);
        $p_name = $stmt_p->fetchColumn();
        if ($p_name) {
            $_SESSION['position_name'] = $p_name;
        }
    }
    $is_admin = (isset($_SESSION['position_name']) && in_array(strtolower($_SESSION['position_name']), ['administrator', 'admin']))
        || (function_exists('hasPermission') && hasPermission($user_id, 'manage_academic'));

    // Verify access
    if (!$is_admin) {
        $stmt_check = $conn->prepare("SELECT id FROM grade_levels WHERE id = :gid AND teacher_id = :uid");
        $stmt_check->execute([':gid' => $grade_id, ':uid' => $user_id]);
        if (!$stmt_check->fetch()) {
            header("Location: ../../views/homeroom/attendance.php?error=Akses+ditolak");
            exit;
        }
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM grade_levels WHERE id = :gid");
        $stmt_check->execute([':gid' => $grade_id]);
        if (!$stmt_check->fetch()) {
            header("Location: ../../views/homeroom/attendance.php?error=Kelas+tidak+ditemukan");
            exit;
        }
    }

    $conn->beginTransaction();
    try {
        foreach ($att_data as $student_id => $data) {
            $status = $data['status'] ?? 'H';
            $notes = $data['notes'] ?? '';

            // Upsert
            $sql = "INSERT INTO daily_student_attendances (student_id, grade_level_id, date, status, notes, created_by)
                    VALUES (:sid, :gid, :date, :status, :notes, :uid)
                    ON DUPLICATE KEY UPDATE status = :status2, notes = :notes2, updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':sid' => $student_id,
                ':gid' => $grade_id,
                ':date' => $date,
                ':status' => $status,
                ':notes' => $notes,
                ':uid' => $user_id,
                ':status2' => $status,
                ':notes2' => $notes
            ]);
        }
        $conn->commit();
        header("Location: ../../views/homeroom/attendance.php?grade_id=$grade_id&date=$date&success=Absensi+berhasil+disimpan");
    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: ../../views/homeroom/attendance.php?grade_id=$grade_id&date=$date&error=Gagal+menyimpan+absensi: " . urlencode($e->getMessage()));
    }
} else {
    header("Location: ../../views/homeroom/attendance.php");
}
exit;
