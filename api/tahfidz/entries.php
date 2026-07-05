<?php
// api/tahfidz/entries.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Tahfidz/MemorizationService.php';
require_once __DIR__ . '/../../config/permission.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0);
if ($user_id > 0 && !hasPermission($user_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki hak akses Tahfidz."]);
    exit;
}

$service = new MemorizationService();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $res = $service->getEntry($id);
                if (!$res) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Entry not found."]);
                } else {
                    echo json_encode(["success" => true, "data" => $res]);
                }
            } else {
                $filters = [
                    'student_id' => isset($_GET['student_id']) ? (int)$_GET['student_id'] : null,
                    'entry_type' => isset($_GET['entry_type']) ? $_GET['entry_type'] : null,
                    'date' => isset($_GET['date']) ? $_GET['date'] : null
                ];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $res = $service->listEntries($filters, $page, $limit);
                echo json_encode(array_merge(["success" => true], $res));
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
            $id = $service->createEntry($input);
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Entry created successfully.", "id" => $id]);
            break;

        case 'PUT':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID is required."]);
                break;
            }
            $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
            $service->updateEntry($id, $input);
            echo json_encode(["success" => true, "message" => "Entry updated successfully."]);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                $input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
                $id = isset($input['id']) ? (int)$input['id'] : 0;
            }
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID is required."]);
                break;
            }
            $service->deleteEntry($id);
            echo json_encode(["success" => true, "message" => "Entry deleted successfully."]);
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
