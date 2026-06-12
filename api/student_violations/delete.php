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

$db = new Database();
$conn = $db->getConnection();

// Basic authentication check: ensure user is a valid employee
$stmtCheck = $conn->prepare("SELECT id FROM employees WHERE id = ?");
$stmtCheck->execute([$user_id]);
if (!$stmtCheck->fetch()) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

$id = $data['id'] ?? $_GET['id'] ?? '';

if (!$id) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'PELANGGARAN ID needed']);
    exit;
}

try {
    $stmtCheck = $conn->prepare("SELECT pelapor, status FROM pelanggaran WHERE id = ?");
    $stmtCheck->execute([$id]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Pelanggaran not found']);
        exit;
    }

    // Admin can delete anything. Pelapor can delete their own.
    $is_admin = hasPermission($user_id, 'can_access_kabid');
    
    if (!$is_admin && $row['pelapor'] != $user_id) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Hanya pelapor atau admin yang dapat menghapus']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM pelanggaran WHERE id = ?");
    $stmt->execute([$id]);

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Pelanggaran berhasil dihapus'
    ]);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

