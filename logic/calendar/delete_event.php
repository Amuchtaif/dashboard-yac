<?php
// logic/calendar/delete_event.php
require_once '../../config/app.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

check_login();

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$delete_scope = !empty($data['delete_scope']) ? $data['delete_scope'] : 'single';

$db = new Database();
$conn = $db->getConnection();

try {
    // Check if event exists and if it has a repeat_group_id
    $checkStmt = $conn->prepare("SELECT id, repeat_group_id FROM academic_calendar WHERE id = ?");
    $checkStmt->execute([$data['id']]);
    $event = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Kegiatan tidak ditemukan']);
        exit;
    }

    if ($delete_scope === 'series' && !empty($event['repeat_group_id'])) {
        $stmt = $conn->prepare("DELETE FROM academic_calendar WHERE repeat_group_id = ?");
        $stmt->execute([$event['repeat_group_id']]);
        $count = $stmt->rowCount();
        echo json_encode(['success' => true, 'message' => "Seluruh rangkaian kegiatan berulang berhasil dihapus ({$count} kegiatan)"]);
    } else {
        $stmt = $conn->prepare("DELETE FROM academic_calendar WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true, 'message' => 'Kegiatan berhasil dihapus dari kalender']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
