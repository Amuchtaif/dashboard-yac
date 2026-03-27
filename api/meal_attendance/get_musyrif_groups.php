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
        SELECT 
            e.id as musyrif_id,
            e.full_name as musyrif_name,
            br.id as room_id,
            br.room_name,
            COUNT(brm.student_id) as total_students
        FROM employees e
        JOIN positions p ON e.position_id = p.id
        JOIN boarding_rooms br ON br.supervisor_id = e.id
        LEFT JOIN boarding_room_members brm ON br.id = brm.room_id
        WHERE p.name LIKE '%Musyrif%'
        GROUP BY e.id, e.full_name, br.id, br.room_name
        ORDER BY br.room_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If detail requested, fetch students for each room
    if (isset($_GET['include_students']) && $_GET['include_students'] == '1') {
        foreach ($groups as &$group) {
            $student_sql = "
                SELECT s.id, s.nama_siswa, s.nomor_induk, s.kelas
                FROM students s
                JOIN boarding_room_members brm ON s.id = brm.student_id
                WHERE brm.room_id = ?
                ORDER BY s.nama_siswa ASC
            ";
            $s_stmt = $conn->prepare($student_sql);
            $s_stmt->execute([$group['room_id']]);
            $group['students'] = $s_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode(["success" => true, "data" => $groups]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
