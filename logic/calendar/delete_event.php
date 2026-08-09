<?php
// logic/calendar/delete_event.php
require_once '../../config/database.php';

header('Content-Type: application/json');

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->prepare("DELETE FROM academic_calendar WHERE id = ?");
    if ($stmt->execute([$data['id']])) {
        echo json_encode(['success' => true, 'message' => 'Kegiatan berhasil dihapus dari kalender']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
