<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$id = $_GET['id'] ?? null;

if ($id) {
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("DELETE FROM employee_assignments WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            header("Location: ../../views/assignments/index.php?success=" . urlencode("Jabatan tambahan berhasil dihapus."));
        } else {
            header("Location: ../../views/assignments/index.php?error=" . urlencode("Gagal menghapus jabatan tambahan."));
        }
    } catch (PDOException $e) {
        header("Location: ../../views/assignments/index.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
} else {
        header("Location: ../../views/assignments/index.php?error=Operasi+gagal");
}
