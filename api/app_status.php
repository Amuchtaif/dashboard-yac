<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$status = "active";
$message = "";

try {
    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'is_maintenance'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['setting_value'] == '1') {
        $status = "maintenance";
        
        $stmt_msg = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'maintenance_message'");
        $stmt_msg->execute();
        $row_msg = $stmt_msg->fetch(PDO::FETCH_ASSOC);
        $message = $row_msg ? $row_msg['setting_value'] : "Aplikasi sedang dalam pemeliharaan. Silakan coba lagi nanti.";
    }
} catch (Exception $e) {
    // Fallback to active if DB fails
}

echo json_encode([
    "status" => $status,
    "message" => $message,
    "timestamp" => date('Y-m-d H:i:s')
]);
