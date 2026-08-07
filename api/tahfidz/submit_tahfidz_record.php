<?php
// api/tahfidz/submit_tahfidz_record.php

date_default_timezone_set('Asia/Jakarta');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Tahfidz/MemorizationService.php';
require_once __DIR__ . '/../../config/permission.php';

$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

$student_id = isset($input['student_id']) ? (int)$input['student_id'] : 0;
$teacher_id = isset($input['teacher_id']) ? (int)$input['teacher_id'] : 0;

if ($student_id <= 0 || $teacher_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Student ID and Teacher ID are required"]);
    exit;
}

if (!hasPermission($teacher_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki akses Tahfidz."]);
    exit;
}

try {
    $service = new MemorizationService();
    $id = $service->createEntry($input);
    echo json_encode([
        "success" => true,
        "message" => "Setoran hafalan berhasil disimpan",
        "id" => $id
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
