<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_GET['id'];
    $action = $_GET['action'];

    // Determine status
    $status = ($action === 'approve') ? 'Approved' : (($action === 'reject') ? 'Rejected' : '');

    if ($status) {
        $approved_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $approved_at = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("UPDATE permits SET status = :status, approved_by = :approved_by, approved_at = :approved_at WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':approved_by', $approved_by);
        $stmt->bindParam(':approved_at', $approved_at);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            $msg = ($status === 'Approved') ? 'disetujui' : 'ditolak';
            header("Location: ../../views/permits/index.php?success=Pengajuan+izin+berhasil+" . $msg);
        } else {
            header("Location: ../../views/permits/index.php?error=Gagal+memperbarui+status");
        }
    } else {
        header("Location: ../../views/permits/index.php?error=Aksi+tidak+valid");
    }
} else {
        header("Location: ../../views/permits/index.php?error=Operasi+gagal");
}
exit;
