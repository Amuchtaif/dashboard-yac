<?php
// api/boarding/get_holidays.php
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

    $now = date('Y-m-d');
    
    // Fetch holidays that are upcoming or active
    $query = "SELECT * FROM boarding_holidays WHERE status = 'Aktif' ORDER BY start_date ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($holidays),
        "data" => $holidays,
        "current_date" => $now
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
