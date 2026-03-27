<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Auth check: support both session and request parameter (for Flutter app)
$user_id = $_SESSION['user_id'] ?? $data['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!hasPermission($user_id, 'can_access_kesantrian')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$id = $data['id'] ?? '';
$santri_id = $data['santri_id'] ?? '';
$kategori_id = $data['kategori_id'] ?? '';
$deskripsi = $data['deskripsi'] ?? '';
$tanggal = $data['tanggal'] ?? '';
$lokasi = $data['lokasi'] ?? '';
$status = $data['status'] ?? '';

if (!$id || !$santri_id || !$deskripsi || !$tanggal || !$kategori_id) {
    echo json_encode(['success' => false, 'message' => 'ID, santri, kategori, deskripsi, dan tanggal wajib ada']);
    exit;
}

try {
    // Access control
    $stmtCheck = $conn->prepare("SELECT pelapor FROM pelanggaran WHERE id = ?");
    $stmtCheck->execute([$id]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }

    $is_admin = hasPermission($user_id, 'can_access_kabid');
    if (!$is_admin && $row['pelapor'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE pelanggaran SET santri_id=?, kategori_id=?, deskripsi=?, tanggal_pelanggaran=?, lokasi=?, status=? 
                            WHERE id = ?");
    $stmt->execute([$santri_id, $kategori_id, $deskripsi, $tanggal, $lokasi, $status, $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Data pelanggaran diperbarui'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
