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
    $conn->beginTransaction();

    // Deactivate all
    $conn->query("UPDATE academic_years SET is_active = 0");

    // Activate selected
    $stmt = $conn->prepare("UPDATE academic_years SET is_active = 1 WHERE id = :id");
    $stmt->execute([':id' => $id]);

    $conn->commit();

    redirect('views/academic_years/index.php?success=' . urlencode('Tahun ajaran aktif berhasil diubah.'));
} catch (Exception $e) {
    $conn->rollBack();
    redirect('views/academic_years/index.php?error=' . urlencode('Gagal mengubah status: ' . $e->getMessage()));
}
