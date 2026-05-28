<?php
// api/get_my_permits.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!isset($_GET['user_id'])) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "User ID required"]);
        exit();
    }

    $user_id = $_GET['user_id'];

    // Fetch all permit records where employee_id matches user_id
    // Ordering: Newest first (created_at DESC)
    $query = "SELECT * FROM permits WHERE employee_id = :uid ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':uid', $user_id);
    $stmt->execute();

    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    echo json_encode([
        "success" => true,
        "data" => $permits
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}

?>