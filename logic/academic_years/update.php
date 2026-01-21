<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/academic_years/index.php');
}

$db = new Database();
$conn = $db->getConnection();

$id = $_POST['id'] ?? '';
$name = trim($_POST['name'] ?? '');
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$status = $_POST['status'] ?? 'Inactive';

if (empty($id) || empty($name)) {
    redirect('views/academic_years/index.php?error=' . urlencode('ID dan Nama tahun ajaran wajib diisi.'));
}

try {
    // If setting as Active, deactivate others first
    if ($status === 'Active') {
        // Prepare to deactivate all others except this one
        $conn->query("UPDATE academic_years SET status = 'Inactive'");
    }

    $sql = "UPDATE academic_years SET name = :name, start_date = :start_date, end_date = :end_date, status = :status WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':status' => $status,
        ':id' => $id
    ]);

    redirect('views/academic_years/index.php?success=' . urlencode('Tahun ajaran berhasil diperbarui.'));

} catch (Exception $e) {
    redirect('views/academic_years/index.php?error=' . urlencode('Gagal memperbarui data: ' . $e->getMessage()));
}
