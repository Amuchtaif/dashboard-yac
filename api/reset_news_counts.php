<?php
// api/reset_news_counts.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Reset counts to 0
    $stmt = $db->prepare("UPDATE news SET likes_count = 0, views_count = 0");
    $stmt->execute();
    
    // Optional: Clear likes table to start fresh
    $db->exec("TRUNCATE TABLE news_likes");

    echo json_encode([
        "success" => true,
        "message" => "Semua hitungan suka dan tayangan telah direset ke 0."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
