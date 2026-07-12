<?php
// api/teacher/get_teachers.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once __DIR__ . '/../../config/db_mysqli.php';

try {

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // ==========================
    // Ambil Tahun Akademik Aktif
    // ==========================
    $activeYearId = 0;

    $yearQuery = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
    $yearResult = $mysqli->query($yearQuery);

    if ($yearResult && $yearResult->num_rows > 0) {
        $activeYearId = (int)$yearResult->fetch_assoc()['id'];
    }

    if ($activeYearId == 0) {
        throw new Exception("Tahun akademik aktif tidak ditemukan.");
    }

    // ==========================
    // Query Guru
    // ==========================
    $sql = "
        SELECT
            e.id,
            e.full_name,
            e.nik AS nip,
            d.name AS division_name,
            u.name AS unit_name,
            p.name AS position_name,
            COALESCE(MIN(s.name), 'GURU') AS subject_name
        FROM employees e
        INNER JOIN class_schedules cs
            ON cs.employee_id = e.id
        LEFT JOIN subjects s
            ON s.id = cs.subject_id
        LEFT JOIN divisions d
            ON d.id = e.division_id
        LEFT JOIN units u
            ON u.id = e.unit_id
        LEFT JOIN positions p
            ON p.id = e.position_id
        WHERE
            e.status = 'active'
            AND cs.academic_year_id = ?
    ";

    if (!empty($search)) {
        $sql .= " AND (
                    e.full_name LIKE ?
                    OR e.nik LIKE ?
                  )";
    }

    $sql .= "
        GROUP BY
            e.id,
            e.full_name,
            e.nik,
            d.name,
            u.name,
            p.name
        ORDER BY e.full_name ASC
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    if (!empty($search)) {

        $like = "%{$search}%";

        $stmt->bind_param(
            "iss",
            $activeYearId,
            $like,
            $like
        );

    } else {

        $stmt->bind_param(
            "i",
            $activeYearId
        );

    }

    $stmt->execute();

    $result = $stmt->get_result();

    $teachers = [];

    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($teachers),
        "data" => $teachers
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

}

$mysqli->close();