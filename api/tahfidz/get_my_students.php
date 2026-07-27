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

$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : (isset($_GET['user_id']) ? $_GET['user_id'] : null);
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$teacher_id && $group_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Teacher ID or Group ID is required"]);
    exit;
}

// Verify permission if teacher_id is provided
if ($teacher_id && !hasPermission($teacher_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki akses Tahfidz."]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch Active Academic Year
    $active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    if (!$active_year_id) {
        $active_year_id = 1;
    }

    $where = [];
    $params = [$active_year_id, $active_year_id, $active_year_id, $active_year_id, $active_year_id];

    if ($teacher_id && $group_id > 0) {
        $where[] = "(hg.teacher_id = ? OR hg.id = ?)";
        $params[] = $teacher_id;
        $params[] = $group_id;
    } elseif ($teacher_id) {
        $where[] = "hg.teacher_id = ?";
        $params[] = $teacher_id;
    } else {
        $where[] = "hg.id = ?";
        $params[] = $group_id;
    }

    $where_sql = implode(" AND ", $where);

    // Query to get students assigned through halaqah_groups and halaqah_members
    // Fetches baseline_juz (from memorization_baselines), total_juz, and last_surah
    $query = "SELECT 
                s.id, 
                s.nama_siswa as full_name, 
                s.nama_siswa as nama_siswa,
                s.nomor_induk as nis,
                gl.name as kelas,
                s.tingkat,
                hg.group_name as halaqah_name,
                COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = ? LIMIT 1), 
                         (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                         0.0) as baseline_juz,
                COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = ? LIMIT 1), 
                         (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                         0.0) as baseline,
                COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = ? LIMIT 1), 
                         (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                         0.0) as initial_juz,
                COALESCE((SELECT COUNT(DISTINCT juz) FROM memorization_entries WHERE student_id = s.id), 0) as total_juz_entries,
                (
                  COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = ? LIMIT 1), 
                           (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                           0.0) 
                  + 
                  ROUND(COALESCE((SELECT SUM(line_count) FROM memorization_entries WHERE student_id = s.id AND entry_type = 'HAFALAN_BARU'), 0) / 300.0, 1)
                ) as total_juz,
                COALESCE((SELECT surah_end FROM memorization_entries WHERE student_id = s.id ORDER BY date DESC, id DESC LIMIT 1), '-') as last_surah
              FROM halaqah_members hm
              JOIN halaqah_groups hg ON hm.group_id = hg.id
              JOIN students s ON hm.student_id = s.id
              LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
              LEFT JOIN grade_levels gl ON sch.class_id = gl.id
              WHERE $where_sql
              ORDER BY s.nama_siswa ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ayName = $conn->query("SELECT name FROM academic_years WHERE id = $active_year_id LIMIT 1")->fetchColumn() ?: "2026/2027";

    $formatted_students = [];
    $filled_count = 0;

    foreach ($rows as $row) {
        $baselineVal = (float)$row['baseline_juz'];
        $isFilled = ($baselineVal > 0);
        if ($isFilled) $filled_count++;

        $formatted_students[] = [
            "id" => (int)$row['id'],
            "student_id" => (int)$row['id'],
            "full_name" => $row['full_name'],
            "nama_siswa" => $row['nama_siswa'],
            "nis" => $row['nis'] ?: '-',
            "kelas" => $row['kelas'] ?: '-',
            "tingkat" => $row['tingkat'],
            "halaqah_name" => $row['halaqah_name'],
            "academic_year_name" => $ayName,
            "baseline_juz" => $baselineVal,
            "baseline" => $baselineVal,
            "initial_juz" => $baselineVal,
            "juz" => $baselineVal,
            "total_juz_entries" => (int)$row['total_juz_entries'],
            "total_juz" => (float)$row['total_juz'],
            "last_surah" => $row['last_surah'],
            "is_filled" => $isFilled,
            "is_set" => $isFilled,
            "has_baseline" => $isFilled,
            "status" => $isFilled ? "Sudah Diisi" : "Belum Diisi",
            "baseline_status" => $isFilled ? "Sudah Diisi" : "Belum Diisi",
            "status_text" => $isFilled ? "Sudah Diisi" : "Belum Diisi"
        ];
    }

    $total = count($formatted_students);

    echo json_encode([
        "success" => true,
        "academic_year" => $ayName,
        "academic_year_id" => (int)$active_year_id,
        "count" => $total,
        "total_students" => $total,
        "filled_count" => $filled_count,
        "progress_pengisian" => "$filled_count dari $total Santri",
        "progress_text" => "$filled_count dari $total Santri",
        "data" => $formatted_students
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
