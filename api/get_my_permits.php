<?php
// api/get_my_permits.php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_GET['user_id'])) {
    echo json_encode(["success" => false, "message" => "User ID required"]);
    exit();
}

$user_id = $_GET['user_id'];

try {
    // Fetch all permit records where employee_id matches user_id
    // Ordering: Newest first (created_at DESC)
    // Assuming created_at column exists based on previous user code.
    // If created_at is missing, we might use id DESC.
    // Spec said: "created_at DESC (Newest first)"
    $query = "SELECT * FROM permits WHERE employee_id = :uid ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':uid', $user_id);
    $stmt->execute();

    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $permits
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>