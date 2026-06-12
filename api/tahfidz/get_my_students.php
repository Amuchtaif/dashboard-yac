<?php
// api/tahfidz/get_my_students.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/permission.php';

$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;

if (!$teacher_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Teacher ID is required"]);
    exit;
}

// Verify permission
if (!hasPermission($teacher_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki akses Tahfidz."]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Query to get students assigned to this teacher through halaqah_groups and halaqah_members
    // We also fetch total_juz and last_surah from tahfidz_memorization
    $query = "SELECT 
                s.id, 
                s.nama_siswa as full_name, 
                s.kelas,
                s.tingkat,
                COALESCE((SELECT COUNT(DISTINCT juz) FROM tahfidz_memorization WHERE student_id = s.id), 0) as total_juz,
                COALESCE((SELECT surah_end FROM tahfidz_memorization WHERE student_id = s.id ORDER BY date DESC, id DESC LIMIT 1), '-') as last_surah
              FROM halaqah_members hm
              JOIN halaqah_groups hg ON hm.group_id = hg.id
              JOIN students s ON hm.student_id = s.id
              WHERE hg.teacher_id = ?
              ORDER BY s.nama_siswa ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute([$teacher_id]);
    $students = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "count" => count($students),
        "data" => $students
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
