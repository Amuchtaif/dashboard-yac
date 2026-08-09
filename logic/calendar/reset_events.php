<?php
// logic/calendar/reset_events.php
require_once '../../config/app.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->prepare("DELETE FROM academic_calendar");
    if ($stmt->execute()) {
        $count = $stmt->rowCount();
        echo json_encode([
            'success' => true,
            'message' => "Berhasil mereset $count data agenda kalender akademik."
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mereset data agenda kalender.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
