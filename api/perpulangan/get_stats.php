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

    // Total students currently at home and breakdown by category
    $query = "
        SELECT category, COUNT(*) as count
        FROM boarding_permits
        WHERE status = 'Disetujui'
        AND :now BETWEEN start_date AND end_date
        GROUP BY category
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':now', $now);
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
