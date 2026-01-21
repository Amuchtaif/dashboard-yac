<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: ../../views/subjects/index.php?success=Mata pelajaran berhasil dihapus");
    } catch (PDOException $e) {
        // Cek jika ada relasi (ON DELETE RESTRICT)
        if ($e->getCode() == "23000") {
            header("Location: ../../views/subjects/index.php?error=Tidak dapat menghapus mata pelajaran karena masih digunakan dalam jadwal");
        } else {
            header("Location: ../../views/subjects/index.php?error=Kesalahan Database: " . $e->getMessage());
        }
    }
} else {
    header("Location: ../../views/subjects/index.php?error=ID tidak valid");
}
?>