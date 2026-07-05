<?php
// api/tahfidz/semester/snapshot.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../app/Services/Tahfidz/SnapshotService.php';

$service = new SnapshotService();

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$academic_year_id = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : 0;
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';

try {
    if ($student_id > 0 && $academic_year_id > 0 && !empty($semester)) {
        $res = $service->getStudentSnapshot($student_id, $academic_year_id, $semester);
        if (!$res) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Snapshot not found for specified student and semester."]);
        } else {
            echo json_encode(["success" => true, "data" => $res]);
        }
    } else {
        $filters = [
            'academic_year_id' => $academic_year_id > 0 ? $academic_year_id : null,
            'student_id' => $student_id > 0 ? $student_id : null,
            'semester' => !empty($semester) ? $semester : null
        ];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $res = $service->listSnapshots($filters, $page, $limit);
        echo json_encode(array_merge(["success" => true], $res));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
