<?php
require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

check_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$meeting_id = $data['meeting_id'] ?? null;
$type = $data['type'] ?? null;
$content = $data['content'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$meeting_id || !$type || !$content || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO meeting_notes (meeting_id, user_id, type, content) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiss", $meeting_id, $user_id, $type, $content);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Catatan berhasil ditambahkan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database: ' . $mysqli->error]);
}
