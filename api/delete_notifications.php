<?php
// api/delete_notifications.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "User ID required"]);
    exit();
}

try {
    // --- Notification cleanup ---
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);

    // --- Dismissed markings ---

    // Dismiss all current permit notifications for this user
    // Incoming permits (approver)
    $stmtInc = $conn->prepare("SELECT p.id FROM permits p WHERE p.approver_id = :uid AND p.status = 'Pending'");
    $stmtInc->execute([':uid' => $user_id]);
    $incoming = $stmtInc->fetchAll(PDO::FETCH_ASSOC);

    foreach ($incoming as $row) {
        $key = "inc_" . $row['id'];
        $stmtDismiss = $conn->prepare("INSERT IGNORE INTO dismissed_notifications (user_id, notification_key) VALUES (:uid, :key)");
        $stmtDismiss->execute([':uid' => $user_id, ':key' => $key]);
    }

    // Updated permits (employee)
    $stmtUpd = $conn->prepare("SELECT p.id FROM permits p WHERE p.employee_id = :uid AND p.status IN ('Approved', 'Rejected') AND (p.approved_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) OR p.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY))");
    $stmtUpd->execute([':uid' => $user_id]);
    $updates = $stmtUpd->fetchAll(PDO::FETCH_ASSOC);

    foreach ($updates as $row) {
        $key = "upd_" . $row['id'];
        $stmtDismiss = $conn->prepare("INSERT IGNORE INTO dismissed_notifications (user_id, notification_key) VALUES (:uid, :key)");
        $stmtDismiss->execute([':uid' => $user_id, ':key' => $key]);
    }

        // Dismiss active meeting invitations (participant)
    try {
        $stmtMeet = $conn->prepare("SELECT meeting_id FROM meeting_participants WHERE employee_id = :uid");
        $stmtMeet->execute([':uid' => $user_id]);
        $meetings = $stmtMeet->fetchAll(PDO::FETCH_ASSOC);

        foreach ($meetings as $row) {
            $key = "meet_" . $row['meeting_id'];
            $stmtDismiss = $conn->prepare("INSERT IGNORE INTO dismissed_notifications (user_id, notification_key) VALUES (:uid, :key)");
            $stmtDismiss->execute([':uid' => $user_id, ':key' => $key]);
        }
    } catch (Exception $e) {
        // Skip if table not ready
    }

    echo json_encode(["success" => true, "message" => "All notifications cleared"]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
