<?php
require_once '../../config/database.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_GET['id'];
    $action = $_GET['action'];

    // Determine status
    $status = ($action === 'approve') ? 'Approved' : (($action === 'reject') ? 'Rejected' : '');

    if ($status) {
        $stmt = $conn->prepare("UPDATE permits SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $status);
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
