<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('views/academic_years/index.php?success=Operasi+berhasil');
}

$db = new Database();
$conn = $db->getConnection();

$name = trim($_POST['name'] ?? '');
$semester = $_POST['semester'] ?? 'Ganjil';
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$is_active = ($_POST['status'] ?? 'Inactive') === 'Active' ? 1 : 0;

if (empty($name)) {
    redirect('views/academic_years/index.php?error=' . urlencode('Nama tahun ajaran wajib diisi.'));
}

try {
    // If setting as Active, deactivate others first
    if ($is_active === 1) {
        $conn->query("UPDATE academic_years SET is_active = 0");
    }

    $stmt = $conn->prepare("INSERT INTO academic_years (name, semester, start_date, end_date, is_active) VALUES (:name, :semester, :start_date, :end_date, :is_active)");
    $stmt->execute([
        ':name' => $name,
        ':semester' => $semester,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':is_active' => $is_active
    ]);

    redirect('views/academic_years/index.php?success=' . urlencode('Tahun ajaran berhasil ditambahkan.'));

} catch (Exception $e) {
    redirect('views/academic_years/index.php?error=' . urlencode('Gagal menyimpan data: ' . $e->getMessage()));
}
