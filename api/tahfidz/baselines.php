<?php
// api/tahfidz/baselines.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Tahfidz/BaselineService.php';
require_once __DIR__ . '/../../config/permission.php';

// Helper function to decode payload input
$raw_input = file_get_contents("php://input");
$json_input = json_decode($raw_input, true);
$input = is_array($json_input) ? array_merge($_POST, $json_input) : $_POST;

// Authentication Check
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($input['user_id']) ? (int)$input['user_id'] : (isset($input['teacher_id']) ? (int)$input['teacher_id'] : 0));
if ($user_id > 0 && !hasPermission($user_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki hak akses Tahfidz."]);
    exit;
}

$service = new BaselineService();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $res = $service->getBaseline($id);
                if (!$res) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Baseline not found."]);
                } else {
                    echo json_encode(["success" => true, "data" => $res]);
                }
            } else {
                $filters = [
                    'academic_year_id' => isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : null,
                    'student_id' => isset($_GET['student_id']) ? (int)$_GET['student_id'] : null,
                    'teacher_id' => isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : null),
                    'group_id' => isset($_GET['group_id']) ? (int)$_GET['group_id'] : null,
                    'search' => isset($_GET['search']) ? $_GET['search'] : null
                ];
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
                $res = $service->listBaselines($filters, $page, $limit);
                echo json_encode($res);
            }
            break;

        case 'POST':
            $id = $service->createBaseline($input);
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Baseline hafalan berhasil disimpan.", "id" => $id]);
            break;

        case 'PUT':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : 0);
            if ($id <= 0) {
                $id = $service->createBaseline($input);
                http_response_code(200);
                echo json_encode(["success" => true, "message" => "Baseline hafalan berhasil disimpan.", "id" => $id]);
                break;
            }
            $service->updateBaseline($id, $input);
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Baseline hafalan berhasil diperbarui."]);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID is required."]);
                break;
            }
            $service->deleteBaseline($id);
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Baseline hafalan berhasil dihapus."]);
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
