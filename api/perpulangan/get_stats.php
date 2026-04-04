<?php
// api/perpulangan/get_stats.php

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

    $now = date('Y-m-d H:i:s');
    $user_id = $_GET['user_id'] ?? $_GET['employee_id'] ?? $_GET['supervisor_id'] ?? $_GET['musrif_id'] ?? null;
    $room_id = isset($_GET['room_id']) ? $_GET['room_id'] : null;

    // Total students currently at home and breakdown by category
    // Filtered by Musrif/Supervisor if user_id or room_id is provided
    $query = "
        SELECT bp.category, COUNT(*) as count
        FROM boarding_permits bp
        JOIN students s ON bp.student_id = s.id
        LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
        LEFT JOIN boarding_rooms br ON brm.room_id = br.id
        WHERE bp.status = 'Disetujui'
        AND :now BETWEEN bp.start_date AND bp.end_date
    ";

    if ($room_id) {
        $query .= " AND br.id = :room_id";
    } elseif ($user_id) {
        $query .= " AND (br.supervisor_id = :user_id OR EXISTS (SELECT 1 FROM boarding_room_supervisors brs WHERE brs.room_id = br.id AND brs.supervisor_id = :user_id_mapping))";
    }

    $query .= " GROUP BY bp.category";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':now', $now);
    
    if ($room_id) {
        $stmt->bindParam(':room_id', $room_id);
    } elseif ($user_id) {
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':user_id_mapping', $user_id);
    }
    
    $stmt->execute();
    $stats_by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_at_home = 0;
    $breakdown = [
        "Izin" => 0,
        "Sakit" => 0,
        "Libur" => 0
    ];

    foreach ($stats_by_category as $row) {
        $total_at_home += (int) $row['count'];
        $breakdown[$row['category']] = (int) $row['count'];
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "total_at_home" => $total_at_home,
            "breakdown" => $breakdown
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>
