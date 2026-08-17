<?php
// api/tahfidz/get_students.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit;
}

try {
    $students = [];
    
    // 1. Get active academic year
    $activeYear = "";
    $activeYearId = 0;

    if (!empty($_GET['academic_year_id'])) {
        $yearIdParam = (int)$_GET['academic_year_id'];
        $yearQuery = "SELECT id, name FROM academic_years WHERE id = $yearIdParam LIMIT 1";
        $yearResult = $mysqli->query($yearQuery);
        if ($yearResult && $yearResult->num_rows > 0) {
            $yearRow = $yearResult->fetch_assoc();
            $activeYearId = (int)$yearRow['id'];
            $activeYear = $yearRow['name'];
        }
    }

    if ($activeYearId === 0) {
        $yearQuery = "SELECT id, name FROM academic_years WHERE is_active = 1 LIMIT 1";
        $yearResult = $mysqli->query($yearQuery);
        if ($yearResult && $yearResult->num_rows > 0) {
            $yearRow = $yearResult->fetch_assoc();
            $activeYearId = (int)$yearRow['id'];
            $activeYear = $yearRow['name'];
        }
    }

    if ($activeYearId === 0) {
        $activeYearId = 1;
    }

    // 2. Fetch active students dynamically via student_class_history matching active academic year
    $tahfidzOnly = isset($_GET['tahfidz_only']) && $_GET['tahfidz_only'] == '1';
    $whereConditions = ["(s.status = 'Aktif' OR LOWER(s.status) = 'aktif' OR s.status LIKE 'Aktif%')"];
    if ($tahfidzOnly) {
        $exclude = ["'TKIT'", "'SDIT'", "'PLAY GROUP'"];
        $exclude_str = implode(',', $exclude);
        $whereConditions[] = "(gl.category NOT IN ($exclude_str) OR s.tingkat NOT IN ($exclude_str) OR s.tingkat IS NULL)";
    }
    $whereSql = "WHERE " . implode(" AND ", $whereConditions);

    // Primary query using JOIN on student_class_history for the active academic year (matching teacher/get_students_by_schedule.php pattern)
    $query = "SELECT s.*, 
                     s.nomor_induk as nis,
                     COALESCE(gl.name, s.kelas, '-') as kelas,
                     COALESCE(gl.category, s.tingkat, '-') as tingkat,
                     sch.class_id,
                     sch.academic_year_id as sch_academic_year_id,
                     COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = $activeYearId LIMIT 1), 
                              (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                              0.0) as baseline_juz,
                     COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = $activeYearId LIMIT 1), 
                              (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                              0.0) as baseline,
                     COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = $activeYearId LIMIT 1), 
                              (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                              0.0) as initial_juz
              FROM students s 
              JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = $activeYearId
              JOIN grade_levels gl ON sch.class_id = gl.id
              $whereSql
              ORDER BY s.nama_siswa ASC";

    $formatted_students = [];
    $filled_count = 0;

    $result = $mysqli->query($query);

    // Fallback if INNER JOIN produces no rows (e.g. students not yet linked to class history)
    if (!$result || $result->num_rows === 0) {
        $fallbackQuery = "SELECT s.*, 
                             s.nomor_induk as nis,
                             COALESCE(gl.name, s.kelas, '-') as kelas,
                             COALESCE(gl.category, s.tingkat, '-') as tingkat,
                             sch.class_id,
                             sch.academic_year_id as sch_academic_year_id,
                             COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = $activeYearId LIMIT 1), 
                                      (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                                      0.0) as baseline_juz,
                             COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = $activeYearId LIMIT 1), 
                                      (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                                      0.0) as baseline,
                             COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = $activeYearId LIMIT 1), 
                                      (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                                      0.0) as initial_juz
                      FROM students s 
                      LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = $activeYearId
                      LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                      $whereSql
                      ORDER BY s.nama_siswa ASC";
        $result = $mysqli->query($fallbackQuery);
    }

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $baselineVal = (float)$row['baseline_juz'];
            $isFilled = ($baselineVal > 0);
            if ($isFilled) $filled_count++;

            $row['id'] = (int)$row['id'];
            $row['student_id'] = (int)$row['id'];
            $row['baseline_juz'] = $baselineVal;
            $row['baseline'] = $baselineVal;
            $row['initial_juz'] = $baselineVal;
            $row['juz'] = $baselineVal;
            $row['is_filled'] = $isFilled;
            $row['is_set'] = $isFilled;
            $row['has_baseline'] = $isFilled;
            $row['status_baseline'] = $isFilled ? "Sudah Diisi" : "Belum Diisi";
            $row['baseline_status'] = $isFilled ? "Sudah Diisi" : "Belum Diisi";
            $row['status_text'] = $isFilled ? "Sudah Diisi" : "Belum Diisi";

            $formatted_students[] = $row;
        }
    } else {
        throw new Exception("Error executing query: " . $mysqli->error);
    }

    $total = count($formatted_students);

    echo json_encode([
        "success" => true,
        "academic_year" => $activeYear,
        "academic_year_id" => (int)$activeYearId,
        "count" => $total,
        "total_students" => $total,
        "filled_count" => $filled_count,
        "progress_pengisian" => "$filled_count dari $total Santri",
        "progress_text" => "$filled_count dari $total Santri",
        "data" => $formatted_students,
        "active_year_debug" => $activeYear 
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
