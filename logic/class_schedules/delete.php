<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("DELETE FROM class_schedules WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: ../../views/class_schedules/index.php?success=Jadwal berhasil dihapus");
    } catch (PDOException $e) {
        header("Location: ../../views/class_schedules/index.php?error=Kesalahan Database: " . $e->getMessage());
    }
} else {
    header("Location: ../../views/class_schedules/index.php?error=ID tidak valid");
}
?>