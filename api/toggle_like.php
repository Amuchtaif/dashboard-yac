<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if($data === null) {
    // Try to get from $_POST if json_decode fails
    $data = new stdClass();
    $data->news_id = $_POST['news_id'] ?? null;
    $data->user_id = $_POST['user_id'] ?? null;
}

if(!empty($data->news_id) && !empty($data->user_id)){
    $news_id = $data->news_id;
    $user_id = $data->user_id;

    try {
        // --- Database checks removed for performance ---

        // 3. Toggle Like logic
        $checkLike = $db->prepare("SELECT id FROM news_likes WHERE news_id = ? AND user_id = ?");
        $checkLike->execute([$news_id, $user_id]);
        
        $status = "";
        if($checkLike->fetch()){
            // Unlike
            $db->prepare("DELETE FROM news_likes WHERE news_id = ? AND user_id = ?")->execute([$news_id, $user_id]);
            $db->prepare("UPDATE news SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?")->execute([$news_id]);
            $status = "unliked";
        } else {
            // Like
            $db->prepare("INSERT INTO news_likes (news_id, user_id) VALUES (?, ?)")->execute([$news_id, $user_id]);
            $db->prepare("UPDATE news SET likes_count = likes_count + 1 WHERE id = ?")->execute([$news_id]);
            $status = "liked";
        }

        echo json_encode([
            "status" => "success", 
            "message" => "Like toggled successfully", 
            "data" => [
                "action" => $status,
                "is_liked" => ($status == "liked")
            ]
        ]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing parameters news_id or user_id"]);
}
?>
