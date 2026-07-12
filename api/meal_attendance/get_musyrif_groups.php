<?php
// api/meal_attendance/get_musyrif_groups.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Query to get Musyrifs and their supervised students
    $sql = "
        SELECT DISTINCT
            e.id as musyrif_id,
            e.full_name as musyrif_name,
            br.id as room_id,
            br.room_name,
            (SELECT COUNT(*) FROM boarding_room_members brm2 WHERE brm2.room_id = br.id) as total_students
        FROM employees e
        JOIN positions p ON e.position_id = p.id
        LEFT JOIN boarding_room_supervisors brs ON e.id = brs.supervisor_id
        LEFT JOIN boarding_rooms br ON (br.id = brs.room_id OR br.supervisor_id = e.id)
        WHERE p.name LIKE '%Musyrif%' AND br.id IS NOT NULL
        ORDER BY br.room_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Active Academic Year
    $active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    if (!$active_year_id) {
        $active_year_id = 1;
    }

    // If detail requested, fetch students for each room
    if (isset($_GET['include_students']) && $_GET['include_students'] == '1') {
        foreach ($groups as &$group) {
            $student_sql = "
                SELECT s.id, s.nama_siswa, s.nomor_induk, gl.name as kelas
                FROM students s
                JOIN boarding_room_members brm ON s.id = brm.student_id
                LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                WHERE brm.room_id = ?
                ORDER BY s.nama_siswa ASC
            ";
            $s_stmt = $conn->prepare($student_sql);
            $s_stmt->execute([$active_year_id, $group['room_id']]);
            $group['students'] = $s_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode(["success" => true, "data" => $groups]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
