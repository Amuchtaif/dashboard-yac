<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

$data = json_decode(file_get_contents('php://input'), true);

// Auth check: support both session and request parameter (for Flutter app)
$user_id = $_SESSION['user_id'] ?? $data['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!hasPermission($user_id, 'can_access_kesantrian')) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if (!$data) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$pelanggaran_id = $data['pelanggaran_id'] ?? '';
$tindakan = $data['tindakan'] ?? '';
$catatan = $data['catatan'] ?? '';
$tanggal = $data['tanggal'] ?? '';
$final_status = $data['status'] ?? 'diproses'; // diproses atau selesai

if (!$pelanggaran_id || !$tindakan || !$tanggal) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tindakan dan tanggal wajib diisi']);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. Insert Followup
    $stmt = $conn->prepare("INSERT INTO tindak_lanjut (pelanggaran_id, tindakan, catatan, tanggal_tindakan, penindak) 
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$pelanggaran_id, $tindakan, $catatan, $tanggal, $user_id]);

    // 2. Update Violation Status
    $stmtUpdate = $conn->prepare("UPDATE pelanggaran SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$final_status, $pelanggaran_id]);

    $conn->commit();

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Tindak lanjut berhasil ditambahkan',
        'status' => $final_status
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

