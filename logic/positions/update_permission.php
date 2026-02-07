<?php
require_once '../../config/database.php';
require_once '../../config/app.php'; // For check_login if needed, though this is an AJAX endpoint

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$id = isset($input['id']) ? intval($input['id']) : 0;
// can_create_meeting should be boolean or 0/1
$can_create_meeting = isset($input['can_create_meeting']) ? intval($input['can_create_meeting']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "UPDATE positions SET can_create_meeting = :can_create WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':can_create', $can_create_meeting, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update permission']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
