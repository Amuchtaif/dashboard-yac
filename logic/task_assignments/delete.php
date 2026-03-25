<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$id = $_GET['id'] ?? null;

if ($id) {
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            header("Location: ../../views/task_assignments/index.php?success=" . urlencode("Tugas berhasil dihapus."));
        } else {
            header("Location: ../../views/task_assignments/index.php?error=" . urlencode("Gagal menghapus tugas."));
        }
    } catch (PDOException $e) {
        header("Location: ../../views/task_assignments/index.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
} else {
        header("Location: ../../views/task_assignments/index.php?error=Operasi+gagal");
}
