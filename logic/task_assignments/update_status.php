<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$id = $_GET['id'] ?? null;
$status = $_GET['status'] ?? null;
$valid_statuses = ['Belum Dimulai', 'Sedang Dikerjakan', 'Selesai', 'Dibatalkan'];

if ($id && $status && in_array($status, $valid_statuses)) {
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("UPDATE assignments SET status = :status, updated_at = NOW() WHERE id = :id");
        $result = $stmt->execute([':status' => $status, ':id' => $id]);

        if ($result) {
            header("Location: ../../views/task_assignments/index.php?success=" . urlencode("Status tugas berhasil diubah menjadi '$status'."));
        } else {
            header("Location: ../../views/task_assignments/index.php?error=" . urlencode("Gagal mengubah status tugas."));
        }
    } catch (PDOException $e) {
        header("Location: ../../views/task_assignments/index.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
} else {
    header("Location: ../../views/task_assignments/index.php?error=" . urlencode("Parameter tidak valid."));
}
