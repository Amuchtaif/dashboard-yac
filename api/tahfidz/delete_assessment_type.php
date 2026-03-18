<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id)) {
    try {
        // Soft delete or hard delete? Usually soft delete is safer.
        // Let's do a soft delete by setting is_active = 0, or just hard delete if requested.
        // The prompt says "delete", so let's do hard delete but often we just deactivate.
        $query = "DELETE FROM tahfidz_assessment_types WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Assessment type deleted successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "Unable to delete assessment type."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Incomplete data. ID is required."]);
}
?>
