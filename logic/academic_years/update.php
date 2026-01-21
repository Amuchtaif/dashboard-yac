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
$semester = $_POST['semester'] ?? 'Ganjil';
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$is_active = ($_POST['status'] ?? 'Inactive') === 'Active' ? 1 : 0;

if (empty($id) || empty($name)) {
    redirect('views/academic_years/index.php?error=' . urlencode('ID dan Nama tahun ajaran wajib diisi.'));
}

try {
    // If setting as Active, deactivate others first
    if ($is_active === 1) {
        $conn->query("UPDATE academic_years SET is_active = 0");
    }

    $sql = "UPDATE academic_years SET name = :name, semester = :semester, start_date = :start_date, end_date = :end_date, is_active = :is_active WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':semester' => $semester,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':is_active' => $is_active,
        ':id' => $id
    ]);

    redirect('views/academic_years/index.php?success=' . urlencode('Tahun ajaran berhasil diperbarui.'));

} catch (Exception $e) {
    redirect('views/academic_years/index.php?error=' . urlencode('Gagal memperbarui data: ' . $e->getMessage()));
}
