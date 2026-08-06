<?php
// api/tahfidz/get_memorization.php

date_default_timezone_set('Asia/Jakarta');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$date = isset($_GET['date']) ? substr($_GET['date'], 0, 10) : null;
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;

try {
    // Get active academic year
    $activeYearId = 0;
    $yearQuery = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
    $yearResult = $mysqli->query($yearQuery);
    if ($yearResult && $yearResult->num_rows > 0) {
        $yearRow = $yearResult->fetch_assoc();
        $activeYearId = (int)$yearRow['id'];
    }

    $memorization_records = [];
    $query = "SELECT m.id, m.student_id, m.teacher_id, m.date, m.surah_start, m.start_ayah AS ayat_start, m.total_baris, m.surah_end, m.end_ayah AS ayat_end, m.juz, m.status, m.notes, m.created_at,
                     s.nama_siswa as student_name, COALESCE(gl.name, s.kelas, '-') as kelas, s.tingkat,
                     e.full_name as teacher_name,
                     m.surah_start as surah_name,
                     m.status as quality
              FROM memorization_entries m
              LEFT JOIN students s ON m.student_id = s.id
              LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = $activeYearId
              LEFT JOIN grade_levels gl ON sch.class_id = gl.id
              LEFT JOIN employees e ON m.teacher_id = e.id
              LEFT JOIN halaqah_members hm ON s.id = hm.student_id
              LEFT JOIN halaqah_groups hg ON hm.group_id = hg.id
              WHERE 1=1";

    $params = [];
    $types = "";

    if ($date) {
        $query .= " AND m.date = ?";
        $params[] = $date;
        $types .= "s";
    }

    if ($student_id) {
        $query .= " AND m.student_id = ?";
        $params[] = $student_id;
        $types .= "i";
    }

    if ($teacher_id) {
        $query .= " AND (m.teacher_id = ? OR hg.teacher_id = ?)";
        $params[] = $teacher_id;
        $params[] = $teacher_id;
        $types .= "ii";
    }

    $query .= " GROUP BY m.id ORDER BY m.date DESC, m.created_at DESC";

    $stmt = $mysqli->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $memorization_records[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($memorization_records),
        "data" => $memorization_records
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
