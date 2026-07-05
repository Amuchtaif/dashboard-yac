<?php
// api/attachments.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../app/Services/Activity/StudentActivityService.php';
require_once __DIR__ . '/../config/permission.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
if ($user_id <= 0) {
    session_start();
    $user_id = $_SESSION['user_id'] ?? 0;
}

if ($user_id > 0 && !hasPermission($user_id, 'manage_activities') && (!isset($_SESSION['position_name']) || $_SESSION['position_name'] !== 'Administrator')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki hak akses mengelola aktivitas."]);
    exit;
}

$service = new StudentActivityService();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'DELETE') {
        $activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
        $attachment_id = isset($_GET['attachment_id']) ? (int)$_GET['attachment_id'] : 0;
        
        if ($activity_id <= 0 || $attachment_id <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Activity ID dan Attachment ID wajib diisi."]);
            exit;
        }

        $service->deleteAttachment($activity_id, $attachment_id);
        echo json_encode(["success" => true, "message" => "Dokumentasi berhasil dihapus."]);
    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed."]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
