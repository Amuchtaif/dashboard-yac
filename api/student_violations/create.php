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

$santri_id = $data['santri_id'] ?? '';
$kategori_id = $data['kategori_id'] ?? '';
$deskripsi = $data['deskripsi'] ?? '';
$tanggal = $data['tanggal'] ?? '';
$lokasi = $data['lokasi'] ?? '';
$status = $data['status'] ?? 'draft';

if (!$santri_id || !$deskripsi || !$tanggal || !$kategori_id) {
    echo json_encode(['success' => false, 'message' => 'Santri, kategori, deskripsi, dan tanggal wajib diisi']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO pelanggaran (santri_id, kategori_id, deskripsi, tanggal_pelanggaran, lokasi, pelapor, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$santri_id, $kategori_id, $deskripsi, $tanggal, $lokasi, $user_id, $status]);

    echo json_encode([
        'success' => true,
        'message' => 'Pelanggaran berhasil dilaporkan',
        'id' => $conn->lastInsertId()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
