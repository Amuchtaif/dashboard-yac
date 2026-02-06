<?php
// logic/meetings/delete.php
header('Content-Type: application/json');
include_once '../../config/db_mysqli.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

// Transaction for safety
$mysqli->begin_transaction();

try {
    // Delete participants first (Constraint usually handles this but manual is safe)
    // Actually schema has ON DELETE CASCADE, so deleting meeting is enough.
    // We rely on ON DELETE CASCADE defined in the previous SQL.

    $stmt = $mysqli->prepare("DELETE FROM meetings WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $mysqli->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>