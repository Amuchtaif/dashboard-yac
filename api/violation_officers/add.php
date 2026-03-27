<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

header('Content-Type: application/json');

check_permission('can_access_kesantrian');

$data = json_decode(file_get_contents('php://input'), true);
$employee_id = $data['employee_id'] ?? '';

if (!$employee_id) {
    echo json_encode(['success' => false, 'message' => 'Pilih karyawan!']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->prepare("INSERT INTO petugas_pelanggaran (employee_id) VALUES (?)");
    $stmt->execute([$employee_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Petugas berhasil ditambahkan'
    ]);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'Karyawan ini sudah terdaftar sebagai petugas']);
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
