<?php
// api/dismiss_notification.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$user_id = $_GET['user_id'] ?? '';
$notification_key = $_GET['notification_key'] ?? '';

if (empty($user_id) || empty($notification_key)) {
    echo json_encode(["success" => false, "message" => "User ID and notification key required"]);
    exit();
}

try {
    // --- Database check removed ---

    $stmt = $conn->prepare("INSERT IGNORE INTO dismissed_notifications (user_id, notification_key) VALUES (:uid, :key)");
    $stmt->execute([':uid' => $user_id, ':key' => $notification_key]);

    echo json_encode(["success" => true, "message" => "Notification dismissed"]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
