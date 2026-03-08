<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/boarding/returns/index.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_return') {
        $student_id = $_POST['student_id'] ?? '';
        $return_date = $_POST['return_date'] ?? '';
        $description = $_POST['description'] ?? '';

        if (empty($student_id) || empty($return_date)) throw new Exception("Santri dan tanggal kembali harus diisi.");

        $stmt = $conn->prepare("INSERT INTO boarding_returns (student_id, return_date, description) VALUES (?, ?, ?)");
        $stmt->execute([$student_id, $return_date, $description]);

        $_SESSION['success'] = "Jadwal kepulangan santri berhasil dicatat.";
    }
    elseif ($action === 'mark_returned') {
        $id = $_POST['id'] ?? '';
        $stmt = $conn->prepare("UPDATE boarding_returns SET status = 'Sudah Kembali' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Santri dikonfirmasi sudah kembali.";
    }
    elseif ($action === 'delete_return') {
        $id = $_POST['id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM boarding_returns WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Data kepulangan berhasil dihapus.";
    }

    header('Location: ../../views/boarding/returns/index.php');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
