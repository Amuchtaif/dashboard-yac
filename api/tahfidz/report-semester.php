<?php
// api/tahfidz/report-semester.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../app/Services/Tahfidz/SemesterReportService.php';
require_once __DIR__ . '/../../config/db_mysqli.php';

$academic_year_id = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;
$unit_id = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : null;
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : null;
$halaqah_id = isset($_GET['halaqah_id']) ? (int)$_GET['halaqah_id'] : null;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

try {
    $reportService = new SemesterReportService();

    // Query active academic year if not specified
    if ($academic_year_id === null) {
        $ay_res = $mysqli->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
        if ($ay_res && $row = $ay_res->fetch_assoc()) {
            $academic_year_id = (int)$row['id'];
        }
    }

    if (!$academic_year_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Academic Year ID is required or no active year found."]);
        exit;
    }

    // Build SQL query to fetch students matching the filters
    $query = "SELECT s.id as student_id, s.nama_siswa, s.kelas, s.tingkat, s.nomor_induk 
              FROM students s";
    
    $where = " WHERE s.status = 'Aktif'";
    $params = [];
    $types = "";

    if ($student_id > 0) {
        $where .= " AND s.id = ?";
        $params[] = $student_id;
        $types .= "i";
    }

    if ($kelas !== null && $kelas !== '') {
        $where .= " AND s.kelas = ?";
        $params[] = $kelas;
        $types .= "s";
    }

    // If unit filter is passed, we check the student's class mappings or simple check if tingkat matches unit
    // For simplicity, we check student's tingkat or education_units
    if ($unit_id > 0) {
        // e.g. map unit to tingkat
    }

    // If halaqah_id is passed, we join with halaqah_members
    if ($halaqah_id > 0) {
        $query .= " JOIN halaqah_members hm ON s.id = hm.student_id";
        $where .= " AND hm.group_id = ?";
        $params[] = $halaqah_id;
        $types .= "i";
    }

    $query .= $where . " ORDER BY s.nama_siswa ASC";

    $stmt = $mysqli->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $students_result = $stmt->get_result();
    
    $reports = [];
    while ($row = $students_result->fetch_assoc()) {
        $sid = (int)$row['student_id'];
        try {
            $rep = $reportService->getSemesterReport($sid, $academic_year_id);
            $reports[] = array_merge($row, $rep);
        } catch (Exception $e) {
            // Student might not have progress setup, skip or return empty values
            $reports[] = array_merge($row, [
                'baseline_awal' => 0.0,
                'target_semester' => 0.0,
                'hafalan_baru' => 0.0,
                'total_hafalan' => 0.0,
                'persentase_target' => 0.0,
                'total_murojaah' => 0,
                'total_setoran' => 0,
                'nilai_tasmi' => 0.0,
                'catatan' => 'Data tidak tersedia.'
            ]);
        }
    }
    $stmt->close();

    echo json_encode([
        "success" => true,
        "data" => $reports
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
