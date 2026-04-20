<?php
// api/meal_attendance/get_students_list.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $meal_type = $_GET['meal_type'] ?? 'Siang';
    $date = $_GET['date'] ?? date('Y-m-d');
    $grade_id = $_GET['grade_id'] ?? null;
    $room_id = $_GET['room_id'] ?? null;
    
    // Pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = " WHERE 1=1 ";
    $filterParams = []; // Parameters used in the WHERE clause

    if ($meal_type === 'Pagi' || $meal_type === 'Malam') {
        $where .= " AND brm.room_id IS NOT NULL ";
    }

    if ($grade_id) {
        $g_stmt = $conn->prepare("SELECT name FROM grade_levels WHERE id = ?");
        $g_stmt->execute([$grade_id]);
        $grade_name = $g_stmt->fetchColumn();

        if ($grade_name) {
            $where .= " AND (gl.id = :grade_id OR s.kelas = :grade_name)";
            $filterParams[':grade_id'] = $grade_id;
            $filterParams[':grade_name'] = $grade_name;
        } else {
            $where .= " AND gl.id = :grade_id";
            $filterParams[':grade_id'] = $grade_id;
        }
    }

    if ($room_id) {
        $where .= " AND brm.room_id = :room_id";
        $filterParams[':room_id'] = $room_id;
    }

    // --- COUNT TOTAL ---
    $count_sql = "
        SELECT COUNT(DISTINCT s.id) 
        FROM students s
        LEFT JOIN grade_levels gl ON s.kelas = gl.name
        LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
        $where
    ";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($filterParams);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // --- FETCH DATA ---
    $sql = "
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk, 
            s.kelas,
            s.tingkat,
            gl.name as grade_name,
            br.room_name,
            ma.id as attendance_id,
            ma.check_time
        FROM students s
        LEFT JOIN grade_levels gl ON s.kelas = gl.name
        LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
        LEFT JOIN boarding_rooms br ON brm.room_id = br.id
        LEFT JOIN meal_attendances ma ON s.id = ma.student_id 
            AND ma.meal_type = :meal_type 
            AND ma.date = :date
        $where
        ORDER BY s.nama_siswa ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);
    // Combine filter params with session params
    $fullParams = array_merge($filterParams, [
        ':meal_type' => $meal_type,
        ':date' => $date
    ]);
    
    foreach ($fullParams as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get overall stats (eaten/remaining) for the full filtered set (without limit)
    $stats_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN ma.id IS NOT NULL THEN 1 ELSE 0 END) as eaten
        FROM students s
        LEFT JOIN grade_levels gl ON s.kelas = gl.name
        LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
        LEFT JOIN meal_attendances ma ON s.id = ma.student_id 
            AND ma.meal_type = :meal_type 
            AND ma.date = :date
        $where
    ";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute($fullParams);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true, 
        "data" => $data,
        "pagination" => [
            "total_rows" => (int)$total_rows,
            "total_pages" => (int)$total_pages,
            "current_page" => (int)$page,
            "limit" => (int)$limit
        ],
        "stats" => [
            "total" => (int)$stats['total'],
            "eaten" => (int)$stats['eaten'],
            "remaining" => (int)$stats['total'] - (int)$stats['eaten']
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
