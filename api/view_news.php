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
    $data = new stdClass();
    $data->news_id = $_POST['news_id'] ?? null;
}

if(!empty($data->news_id)){
    $news_id = $data->news_id;
    try {
        $stmt = $db->prepare("UPDATE news SET views_count = views_count + 1 WHERE id = ?");
        $stmt->execute([$news_id]);
        echo json_encode(["status" => "success", "message" => "View count updated"]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing parameter news_id"]);
}
?>
