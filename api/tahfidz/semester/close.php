<?php
// api/tahfidz/semester/close.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed. Use POST."]);
    exit;
}

require_once __DIR__ . '/../../../app/Services/Tahfidz/SemesterClosingService.php';
require_once __DIR__ . '/../../../config/permission.php';

$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
$user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;

if ($user_id <= 0 || !hasPermission($user_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Hanya Admin Tahfidz atau Kepala Tahfidz yang dapat menutup semester."]);
    exit;
}

$academic_year_id = isset($input['academic_year_id']) ? (int)$input['academic_year_id'] : 0;
$semester = isset($input['semester']) ? $input['semester'] : '';

if ($academic_year_id <= 0 || !in_array($semester, ['Ganjil', 'Genap'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Validation failed: academic_year_id is required, and semester must be Ganjil or Genap."]);
    exit;
}

try {
    $service = new SemesterClosingService();
    $generated = $service->closeSemester($academic_year_id, $semester);
    
    echo json_encode([
        "success" => true,
        "message" => "Semester closed successfully. Created $generated snapshots for active students."
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
