<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

header('Content-Type: application/json');

check_permission('can_access_kesantrian');

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID needed']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->prepare("DELETE FROM petugas_pelanggaran WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Petugas dihapus']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
