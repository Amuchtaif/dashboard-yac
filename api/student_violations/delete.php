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

$id = $data['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'PELANGGARAN ID needed']);
    exit;
}

try {
    $stmtCheck = $conn->prepare("SELECT pelapor, status FROM pelanggaran WHERE id = ?");
    $stmtCheck->execute([$id]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Pelanggaran not found']);
        exit;
    }

    // Admin can delete anything. Pelapor can delete their own.
    $is_admin = hasPermission($user_id, 'can_access_kabid');
    
    if (!$is_admin && $row['pelapor'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Hanya pelapor atau admin yang dapat menghapus']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM pelanggaran WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Pelanggaran berhasil dihapus'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
