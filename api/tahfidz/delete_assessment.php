<?php
header('Content-Type: application/json');
require_once '../../config/app.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan.']);
    exit;
}

$id = $data['id'];

try {
    $stmt = $conn->prepare("DELETE FROM tahfidz_assessments WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Data penilaian berhasil dihapus.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()]);
}
