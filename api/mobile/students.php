<?php
// api/mobile/students.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Activity/StudentActivityService.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Parameter user_id wajib diisi."]);
    exit;
}

$service = new StudentActivityService();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $students = $service->getStudentsForUser($user_id);
        echo json_encode([
            "success" => true,
            "count" => count($students),
            "data" => $students
        ]);
    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
