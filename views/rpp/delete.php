<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("DELETE FROM rpp WHERE id = ?");
        if ($stmt->execute([$id])) {
            header("Location: index.php?success=" . urlencode("Dokumen RPP berhasil dihapus."));
        } else {
            header("Location: index.php?error=" . urlencode("Gagal menghapus dokumen."));
        }
    } catch (PDOException $e) {
        header("Location: index.php?error=" . urlencode("Gagal menghapus data: " . $e->getMessage()));
    }
} else {
    header("Location: index.php");
}
exit;
