<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../../views/boarding/holidays/index.php?success=Operasi+berhasil');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_holiday') {
        $name = $_POST['name'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';

        if (empty($name) || empty($start_date) || empty($end_date)) throw new Exception("Semua data harus diisi.");

        $stmt = $conn->prepare("INSERT INTO boarding_holidays (name, start_date, end_date) VALUES (?, ?, ?)");
        $stmt->execute([$name, $start_date, $end_date]);

        $_SESSION['success'] = "Jadwal libur berhasil ditambahkan.";
    }
    elseif ($action === 'delete_holiday') {
        $id = $_POST['id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM boarding_holidays WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Jadwal libur berhasil dihapus.";
    }

        header('Location: ../../views/boarding/holidays/index.php?success=Operasi+berhasil');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
