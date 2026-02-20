<?php
// api/teacher/get_teachers.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// Pastikan file config ada. Karena berada di api/teacher/, maka ../../config/
include_once '../../config/db_mysqli.php';

try {
    // Ambil data pencarian jika ada
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Query untuk mengambil pegawai yang memiliki jadwal di class_schedules (Guru)
    $sql = "SELECT DISTINCT e.id, e.full_name, e.nik as nip, d.name as division_name, u.name as unit_name, p.name as position_name
            FROM employees e
            JOIN class_schedules cs ON e.id = cs.employee_id
            LEFT JOIN divisions d ON e.division_id = d.id
            LEFT JOIN units u ON e.unit_id = u.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.status = 'active'";


    if (!empty($search)) {
        $searchSafe = $mysqli->real_escape_string($search);
        $sql .= " AND (e.full_name LIKE '%$searchSafe%' OR e.nik LIKE '%$searchSafe%')";
    }

    $sql .= " ORDER BY e.full_name ASC";

    $result = $mysqli->query($sql);
    if (!$result) {
        throw new Exception("Query Error: " . $mysqli->error);
    }

    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        // Ambil satu mata pelajaran yang diajar untuk label
        $emp_id = $row['id'];
        $subject_sql = "SELECT s.name 
                        FROM class_schedules cs 
                        JOIN subjects s ON cs.subject_id = s.id 
                        WHERE cs.employee_id = $emp_id 
                        LIMIT 1";
        $subject_result = $mysqli->query($subject_sql);
        
        if ($subject_result && $sub_row = $subject_result->fetch_assoc()) {
            $row['subject_name'] = $sub_row['name'];
        } else {
            $row['subject_name'] = "GURU";
        }
        
        $teachers[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($teachers),
        "data" => $teachers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$mysqli->close();
?>
