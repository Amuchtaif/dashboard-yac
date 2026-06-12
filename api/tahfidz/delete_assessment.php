<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// Since this is called from Flutter, we might not have a session.
// We can optionally pass teacher_id for permission check if needed.

$db = new Database();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true) ?? [];

if (!$data || empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan.']);
    exit;
}

$id = $data['id'];

try {
    $stmt = $conn->prepare("DELETE FROM tahfidz_assessments WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Data penilaian berhasil dihapus.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()]);
}
?>
