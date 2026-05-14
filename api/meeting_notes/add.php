<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->meeting_id) &&
    !empty($data->user_id) &&
    !empty($data->type) &&
    !empty($data->content)
) {
    $query = "INSERT INTO meeting_notes (meeting_id, user_id, type, content) VALUES (:meeting_id, :user_id, :type, :content)";
    $stmt = $db->prepare($query);

    $stmt->bindParam(":meeting_id", $data->meeting_id);
    $stmt->bindParam(":user_id", $data->user_id);
    $stmt->bindParam(":type", $data->type);
    $stmt->bindParam(":content", $data->content);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(array("success" => true, "message" => "Catatan berhasil ditambahkan."));
    } else {
        http_response_code(503);
        echo json_encode(array("success" => false, "message" => "Gagal menambahkan catatan."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Data tidak lengkap."));
}
?>
