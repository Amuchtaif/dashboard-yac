<?php
// api/meal_attendance/get_list.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $date = $_GET['date'] ?? date('Y-m-d');
    $type = $_GET['meal_type'] ?? '';

    // Fetch Active Academic Year
    $active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    if (!$active_year_id) {
        $active_year_id = 1;
    }

    $sql = "
        SELECT 
            ma.id, 
            ma.meal_type, 
            ma.date, 
            ma.check_time, 
            s.nama_siswa, 
            s.nomor_induk,
            gl.name as kelas
        FROM meal_attendances ma
        JOIN students s ON ma.student_id = s.id
        LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
        LEFT JOIN grade_levels gl ON sch.class_id = gl.id
        WHERE ma.date = :date
    ";

    $params = [':date' => $date, ':active_year_id' => $active_year_id];

    if (!empty($type)) {
        $sql .= " AND ma.meal_type = :type";
        $params[':type'] = $type;
    }

    $sql .= " ORDER BY ma.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
