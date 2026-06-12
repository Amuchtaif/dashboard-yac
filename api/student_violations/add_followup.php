<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

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

$pelanggaran_id = $data['pelanggaran_id'] ?? $data['violation_id'] ?? '';
$tindakan = $data['tindakan'] ?? $data['action'] ?? '';
$catatan = $data['catatan'] ?? $data['notes'] ?? $data['note'] ?? '';
$tanggal = $data['tanggal'] ?? $data['date'] ?? '';
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
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

