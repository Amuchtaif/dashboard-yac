<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->name)) {
    try {
        if (!empty($data->id)) {
            // Update
            $query = "UPDATE tahfidz_assessment_types SET name = :name, is_active = :is_active WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $data->id);
        } else {
            // Insert
            $query = "INSERT INTO tahfidz_assessment_types (name, is_active) VALUES (:name, :is_active)";
            $stmt = $db->prepare($query);
        }
        
        $stmt->bindParam(':name', $data->name);
        $is_active = isset($data->is_active) ? $data->is_active : 1;
        $stmt->bindParam(':is_active', $is_active);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Assessment type saved successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "Unable to save assessment type."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Incomplete data. Name is required."]);
}
?>
