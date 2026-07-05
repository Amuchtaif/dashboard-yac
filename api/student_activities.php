<?php
// api/student_activities.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS");
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
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $action = isset($_GET['action']) ? $_GET['action'] : '';

            if ($action === 'stats') {
                $filters = [
                    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : null,
                    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : null
                ];
                $stats = $service->getDashboardStats($filters);
                echo json_encode(["success" => true, "data" => $stats]);
            } elseif ($id > 0) {
                $res = $service->getActivity($id);
                if (!$res) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Aktivitas tidak ditemukan."]);
                } else {
                    echo json_encode(["success" => true, "data" => $res]);
                }
            } else {
                $filters = [
                    'student_id' => isset($_GET['student_id']) ? (int)$_GET['student_id'] : null,
                    'activity_type_id' => isset($_GET['activity_type_id']) ? (int)$_GET['activity_type_id'] : null,
                    'status' => isset($_GET['status']) ? $_GET['status'] : null,
                    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : null,
                    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : null,
                    'created_by' => isset($_GET['created_by']) ? (int)$_GET['created_by'] : null,
                    'class' => isset($_GET['class']) ? $_GET['class'] : null,
                    'unit' => isset($_GET['unit']) ? $_GET['unit'] : null,
                    'search' => isset($_GET['search']) ? $_GET['search'] : null
                ];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $res = $service->listActivities($filters, $page, $limit);
                echo json_encode(array_merge(["success" => true], $res));
            }
            break;

        case 'PUT':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID wajib diisi."]);
                break;
            }
            $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
            $service->updateActivity($id, $input, $user_id);
            echo json_encode(["success" => true, "message" => "Aktivitas berhasil diperbarui."]);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID wajib diisi."]);
                break;
            }
            $service->deleteActivity($id);
            echo json_encode(["success" => true, "message" => "Aktivitas berhasil dihapus."]);
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
