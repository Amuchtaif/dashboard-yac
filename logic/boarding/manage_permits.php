<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/boarding/permits/index.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_permit') {
        $student_id = $_POST['student_id'] ?? '';
        $category = $_POST['category'] ?? 'Izin';
        $reason = $_POST['reason'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';

        if (empty($student_id) || empty($reason) || empty($start_date) || empty($end_date)) throw new Exception("Semua data harus diisi.");

        $stmt = $conn->prepare("INSERT INTO boarding_permits (student_id, category, reason, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $category, $reason, $start_date, $end_date]);

        $_SESSION['success'] = "Permohonan izin berhasil diajukan.";
    }
    elseif ($action === 'update_status') {
        $id = $_POST['id'] ?? '';
        $status = $_POST['status'] ?? '';
        $approved_by = $_SESSION['user_id'];

        if (empty($id) || empty($status)) throw new Exception("Data tidak lengkap.");

        $stmt = $conn->prepare("UPDATE boarding_permits SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->execute([$status, $approved_by, $id]);

        $_SESSION['success'] = "Status izin berhasil diperbarui.";
    }
    elseif ($action === 'delete_permit') {
        $id = $_POST['id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM boarding_permits WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Data izin berhasil dihapus.";
    }

    header('Location: ../../views/boarding/permits/index.php');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
