<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

header('Content-Type: application/json');

// Auth check: support both session and request parameter (for Flutter app)
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

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

try {
    $stmt = $conn->query("SELECT id, nama_kategori, poin FROM kategori_pelanggaran ORDER BY poin ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Data kategori berhasil dimuat',
        'data' => $categories
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
