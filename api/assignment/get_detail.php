<?php
// api/assignment/get_detail.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $id = $_GET['id'] ?? null;

    if (!$id) {
        echo json_encode(["status" => "error", "message" => "ID penugasan tidak ditemukan."]);
        exit();
    }

    $query = "SELECT a.*, creator.full_name as creator_name, cp.name as creator_position, creator.profile_photo as creator_photo,
                     assignee.full_name as assignee_name, ap.name as assignee_position, assignee.profile_photo as assignee_photo
              FROM assignments a
              LEFT JOIN employees creator ON a.created_by = creator.id
              LEFT JOIN positions cp ON creator.position_id = cp.id
              LEFT JOIN employees assignee ON a.assigned_to = assignee.id
              LEFT JOIN positions ap ON assignee.position_id = ap.id
              WHERE a.id = :id";

    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(["status" => "error", "message" => "Tugas tidak ditemukan."]);
    } else {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = str_replace('/api/assignment/get_detail.php', '', $scriptName);
        $baseUrl = $protocol . "://" . $host . $basePath . "/";

        // Creator Avatar
        if (!empty($task['creator_photo'])) {
            $task['creator_avatar'] = $baseUrl . "uploads/profile_photos/" . $task['creator_photo'];
        } else {
            $task['creator_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($task['creator_name']) . "&background=random";
        }

        // Assignee Avatar
        if (!empty($task['assignee_photo'])) {
            $task['assignee_avatar'] = $baseUrl . "uploads/profile_photos/" . $task['assignee_photo'];
        } else {
            $task['assignee_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($task['assignee_name']) . "&background=random";
        }

        // Task Attachment URL
        if (!empty($task['attachment_path'])) {
            $task['attachment'] = $baseUrl . "uploads/assignments/" . $task['attachment_path'];
        } elseif (!empty($task['attachment'])) {
            $task['attachment'] = $baseUrl . "uploads/assignments/" . $task['attachment'];
        }

        // Report Attachment URL
        if (!empty($task['report_attachment'])) {
            $task['report_attachment_url'] = $baseUrl . "uploads/assignments/" . $task['report_attachment'];
        }

        echo json_encode(["status" => "success", "data" => $task]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal Server Error: " . $e->getMessage()]);
}
