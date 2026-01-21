<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$id = $_GET['id'] ?? '';

if (empty($id)) {
    redirect('views/academic_years/index.php?error=' . urlencode('Invalid ID.'));
}

$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->prepare("DELETE FROM academic_years WHERE id = :id");
    $stmt->execute([':id' => $id]);

    redirect('views/academic_years/index.php?success=' . urlencode('Tahun ajaran berhasil dihapus.'));
} catch (Exception $e) {
    redirect('views/academic_years/index.php?error=' . urlencode('Gagal menghapus data: ' . $e->getMessage()));
}
