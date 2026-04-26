<?php
// api/work_reports/get_categories.php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT id, name FROM work_report_categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    echo json_encode([
        "success" => true,
        "data" => $categories
    ]);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
