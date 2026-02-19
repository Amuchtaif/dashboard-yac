<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id) {
    $db = new Database();
    $conn = $db->getConnection();

    // Cek apakah jam ini digunakan di jadwal pelajaran
    $stmt_usage = $conn->prepare("SELECT id FROM class_schedules WHERE lesson_period_id = ? LIMIT 1");
    $stmt_usage->execute([$id]);

    if ($stmt_usage->rowCount() > 0) {
        header("Location: ../../views/lesson_periods/index.php?error=Tidak dapat menghapus jam karena masih digunakan dalam Jadwal Pelajaran.");
        exit;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM lesson_periods WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: ../../views/lesson_periods/index.php?success=Jam pelajaran berhasil dihapus");
    } catch (PDOException $e) {
        header("Location: ../../views/lesson_periods/index.php?error=Kesalahan Database: " . $e->getMessage());
    }
} else {
    header("Location: ../../views/lesson_periods/index.php?error=ID tidak valid");
}
?>
