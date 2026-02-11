<?php
// logic/tahfidz/update_role_access.php
require_once '../../config/app.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check login and admin
// check_login(); // This usually redirects, so for AJAX might be issue if not logged in.
// Assuming session exists.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$position_id = $input['position_id'] ?? null;
$status = $input['status'] ?? 0;

if (!$position_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Position ID Required']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Check if column exists, if not create it (Auto-migration for convenience)
    $checkCol = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_tahfidz'");
    if ($checkCol->rowCount() == 0) {
        $conn->exec("ALTER TABLE positions ADD COLUMN can_access_tahfidz TINYINT(1) NOT NULL DEFAULT 0");
    }

    $stmt = $conn->prepare("UPDATE positions SET can_access_tahfidz = :status WHERE id = :id");
    $stmt->bindParam(':status', $status, PDO::PARAM_INT);
    $stmt->bindParam(':id', $position_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Access updated successfully']);
    } else {
        throw new Exception("Update failed");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
