<?php
// api/activity_types.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../app/Services/Activity/ActivityTypeService.php';
require_once __DIR__ . '/../config/permission.php';

// Authenticate via session or request parameter
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

$service = new ActivityTypeService();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $res = $service->getType($id);
                if (!$res) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Jenis aktivitas tidak ditemukan."]);
                } else {
                    echo json_encode(["success" => true, "data" => $res]);
                }
            } else {
                $filters = [
                    'type' => isset($_GET['type']) ? $_GET['type'] : null,
                    'is_active' => isset($_GET['is_active']) ? $_GET['is_active'] : null,
                    'search' => isset($_GET['search']) ? $_GET['search'] : null
                ];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $res = $service->listTypes($filters, $page, $limit);
                echo json_encode(array_merge(["success" => true], $res));
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
            $id = $service->createType($input);
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Jenis aktivitas berhasil dibuat.", "id" => $id]);
            break;

        case 'PUT':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID wajib diisi."]);
                break;
            }
            $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
            $service->updateType($id, $input);
            echo json_encode(["success" => true, "message" => "Jenis aktivitas berhasil diperbarui."]);
            break;

        case 'PATCH':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $action = isset($_GET['action']) ? $_GET['action'] : '';
            if ($id <= 0 || $action !== 'status') {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID dan aksi status wajib diisi."]);
                break;
            }
            $service->toggleStatus($id);
            echo json_encode(["success" => true, "message" => "Status jenis aktivitas berhasil diperbarui."]);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID wajib diisi."]);
                break;
            }
            $service->deleteType($id);
            echo json_encode(["success" => true, "message" => "Jenis aktivitas berhasil dihapus."]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Method not allowed."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
