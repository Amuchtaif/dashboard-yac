<?php
// logic/meetings/toggle_status.php
header('Content-Type: application/json');
include_once '../../config/db_mysqli.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? '';
$current_status = $input['current_status'] ?? ''; // invited, present

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$new_status = ($current_status === 'present') ? 'invited' : 'present';
$attendance_time = ($new_status === 'present') ? date('Y-m-d H:i:s') : NULL;

$sql = "UPDATE meeting_participants SET status = ?, attendance_time = ? WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ssi", $new_status, $attendance_time, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'new_status' => $new_status]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
?>