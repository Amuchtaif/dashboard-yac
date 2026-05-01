<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("DELETE FROM tahfidz_assessments WHERE id = ?");
        if ($stmt->execute([$id])) {
            header("Location: ../../views/tahfidz/assessments.php?success=Data penilaian berhasil dihapus");
            exit;
        } else {
            header("Location: ../../views/tahfidz/assessments.php?error=Gagal menghapus data");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: ../../views/tahfidz/assessments.php?error=Gagal menghapus data: " . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: ../../views/tahfidz/assessments.php");
    exit;
}
