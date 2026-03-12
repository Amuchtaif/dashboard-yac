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

if (!$data || empty($data['name'])) {
    echo json_encode(['success' => false, 'message' => 'Nama penilaian harus diisi.']);
    exit;
}

$id = $data['id'] ?? null;
$name = $data['name'];
$description = $data['description'] ?? null;
$is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

try {
    if ($id) {
        $stmt = $conn->prepare("UPDATE tahfidz_assessment_types SET name = ?, description = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $description, $is_active, $id]);
        $message = "Jenis penilaian berhasil diperbarui.";
    } else {
        $stmt = $conn->prepare("INSERT INTO tahfidz_assessment_types (name, description, is_active) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $is_active]);
        $message = "Jenis penilaian baru berhasil ditambahkan.";
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
