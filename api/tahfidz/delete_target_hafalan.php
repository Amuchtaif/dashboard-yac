<?php
// api/tahfidz/delete_target_hafalan.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if ($data && !empty($data->id)) {
    try {
        $query = "DELETE FROM target_hafalan WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', intval($data->id), PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Target hafalan berhasil dihapus."]);
        } else {
            echo json_encode(["success" => false, "message" => "Gagal menghapus target hafalan."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID wajib dikirim."]);
}
?>
