<?php
// api/perpulangan/get_active.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $user_id = $_GET['user_id'] ?? $_GET['employee_id'] ?? $_GET['supervisor_id'] ?? $_GET['musrif_id'] ?? null;
    $room_id = isset($_GET['room_id']) ? $_GET['room_id'] : null;

    // Fetch all relevant permits (Pending, Active, and Recent)
    // We use LEFT JOIN so that students without rooms still appear if no filter is applied
    $query = "
        SELECT bp.id, bp.student_id, s.nama_siswa, s.kelas, s.foto,
               bp.category, bp.reason, bp.start_date, bp.end_date, bp.status, bp.created_at,
               br.room_name as asrama
        FROM boarding_permits bp
        JOIN students s ON bp.student_id = s.id
        LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
        LEFT JOIN boarding_rooms br ON brm.room_id = br.id
        WHERE 1=1
    ";

    $params = [];
    if ($search) {
        $query .= " AND s.nama_siswa LIKE :search";
        $params[':search'] = "%$search%";
    }
    
    if ($room_id) {
        $query .= " AND br.id = :room_id";
        $params[':room_id'] = $room_id;
    } elseif ($user_id && $user_id != '0') {
        // Only filter by supervisor if user_id is provided and valid
        $query .= " AND (br.supervisor_id = :user_id OR EXISTS (SELECT 1 FROM boarding_room_supervisors brs WHERE brs.room_id = br.id AND brs.supervisor_id = :user_id_mapping))";
        $params[':user_id'] = $user_id;
        $params[':user_id_mapping'] = $user_id;
    }

    $query .= " GROUP BY bp.id";
    $query .= " ORDER BY bp.created_at DESC";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->execute();
    $active_permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($active_permits),
        "data" => $active_permits
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>
