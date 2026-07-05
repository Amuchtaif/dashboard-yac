<?php
// api/mobile/attachments.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Activity/StudentActivityService.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Parameter user_id wajib diisi."]);
    exit;
}

$service = new StudentActivityService();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : (isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0);
        $caption = isset($_POST['caption']) ? $_POST['caption'] : '';

        if ($activity_id <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Activity ID wajib diisi."]);
            exit;
        }

        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "File attachment wajib diupload."]);
            exit;
        }

        $file = $_FILES['file'];
        if (is_array($file['name'])) {
            $uploaded_files = [];
            $total_files = count($file['name']);
            for ($i = 0; $i < $total_files; $i++) {
                $single_file = [
                    'name' => $file['name'][$i],
                    'type' => $file['type'][$i],
                    'tmp_name' => $file['tmp_name'][$i],
                    'error' => $file['error'][$i],
                    'size' => $file['size'][$i],
                ];
                $res = $service->addAttachment($activity_id, $single_file, $caption, $user_id);
                $uploaded_files[] = $res;
            }
            echo json_encode(["success" => true, "message" => "Semua dokumentasi berhasil diupload.", "data" => $uploaded_files]);
        } else {
            $res = $service->addAttachment($activity_id, $file, $caption, $user_id);
            echo json_encode(["success" => true, "message" => "Dokumentasi berhasil diupload.", "data" => $res]);
        }
    } elseif ($method === 'DELETE') {
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
