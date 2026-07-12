<?php
// api/tahfidz/get_student_attendance.php

if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
// Adjust path if necessary based on your folder structure
// Assuming this file is in api/tahfidz/ and config is in config/
include_once __DIR__ . '/../../config/db_mysqli.php';

$date = isset($_GET['date']) ? $_GET['date'] : null;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$session = isset($_GET['session']) ? $_GET['session'] : null;
$group_id = isset($_GET['group_id']) ? $_GET['group_id'] : null;

try {
    // Get active academic year
    $activeYearId = 0;
    $yearQuery = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
    $yearResult = $mysqli->query($yearQuery);
    if ($yearResult && $yearResult->num_rows > 0) {
        $yearRow = $yearResult->fetch_assoc();
        $activeYearId = (int)$yearRow['id'];
    }

    $attendance_records = [];
    $params = [];
    $types = "";

    if ($group_id) {
        // Jika date dan session tidak disertakan, berikan nilai default untuk mencegah data duplikat/tidak relevan bergabung
        if (!$date) {
            $date = date('Y-m-d');
        }
        if (!$session) {
            $current_hour = (int)date('H');
            $session = ($current_hour >= 12) ? 'Sore' : 'Pagi';
        }

        // Ambil semua santri anggota kelompok halaqah, lalu LEFT JOIN ke data absensi mereka
        $query = "SELECT 
                    ta.id,
                    s.id as student_id,
                    ta.date,
                    ta.status,
                    ta.session,
                    ta.teacher_id,
                    ta.created_at,
                    s.nama_siswa as student_name,
                    gl.name as kelas,
                    s.tingkat
                  FROM halaqah_members hm
                  INNER JOIN students s ON hm.student_id = s.id
                  LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                  LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                  LEFT JOIN tahfidz_attendance ta ON ta.student_id = s.id 
                      AND ta.date = ? 
                      AND ta.session = ?
                  WHERE hm.group_id = ?
                  ORDER BY s.nama_siswa ASC";
        
        $params[] = $activeYearId;
        $params[] = $date;
        $params[] = $session;
        $params[] = $group_id;
        $types .= "issi";

    } else {
        $query = "SELECT ta.*, s.nama_siswa as student_name, gl.name as kelas, s.tingkat 
                  FROM tahfidz_attendance ta 
                  LEFT JOIN students s ON ta.student_id = s.id 
                  LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = $activeYearId AND sch.status = 'ACTIVE'
                  LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                  WHERE 1=1";

        if ($date) {
            $query .= " AND ta.date = ?";
            $params[] = $date;
            $types .= "s";
        }

        if ($student_id) {
            $query .= " AND ta.student_id = ?";
            $params[] = $student_id;
            $types .= "i";
        }

        if ($session) {
            $query .= " AND ta.session = ?";
            $params[] = $session;
            $types .= "s";
        }

        $query .= " ORDER BY ta.date DESC, s.nama_siswa ASC";
    }

    if (isset($mysqli)) {
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception("Gagal mempersiapkan query (prepare failed): " . $mysqli->error);
        }

        if (!empty($params)) {
            // PHP 8.1+ mendukung eksekusi dengan parameter array secara langsung
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80100) {
                $stmt->execute($params);
            } else {
                $bind_names = [];
                $bind_names[] = $types;
                for ($i = 0; $i < count($params); $i++) {
                    $bind_names[] = &$params[$i];
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_names);
                $stmt->execute();
            }
        } else {
            $stmt->execute();
        }

        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }

        echo json_encode([
            "success" => true,
            "count" => count($attendance_records),
            "data" => $attendance_records
        ]);
    } else {
        throw new Exception("Koneksi database gagal");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
