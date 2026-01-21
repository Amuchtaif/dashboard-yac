<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/academic_years/index.php');
}

$db = new Database();
$conn = $db->getConnection();

$name = trim($_POST['name'] ?? '');
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$status = $_POST['status'] ?? 'Inactive';

if (empty($name)) {
    redirect('views/academic_years/index.php?error=' . urlencode('Nama tahun ajaran wajib diisi.'));
}

try {
    // If setting as Active, deactivate others first
    if ($status === 'Active') {
        $conn->query("UPDATE academic_years SET status = 'Inactive'");
    }

    $stmt = $conn->prepare("INSERT INTO academic_years (name, start_date, end_date, status) VALUES (:name, :start_date, :end_date, :status)");
    $stmt->execute([
        ':name' => $name,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':status' => $status
    ]);

    redirect('views/academic_years/index.php?success=' . urlencode('Tahun ajaran berhasil ditambahkan.'));

} catch (Exception $e) {
    redirect('views/academic_years/index.php?error=' . urlencode('Gagal menyimpan data: ' . $e->getMessage()));
}
