<?php
// api/inventory/locations/create.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once __DIR__ . '/../../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $data = json_decode(file_get_contents("php://input"));
    $name = $data->name ?? $_POST['name'] ?? '';
    $parent_id = $data->parent_id ?? $_POST['parent_id'] ?? null;
    
    if (empty($name)) {
        echo json_encode(["success" => false, "message" => "Name is required."]);
        exit;
    }

    if ($parent_id === "") $parent_id = null; // Normalize empty string to null

    $stmt = $conn->prepare("INSERT INTO inventory_locations (name, parent_id) VALUES (?, ?)");
    if ($stmt->execute([$name, $parent_id])) {
        echo json_encode(["success" => true, "message" => "Location created successfully.", "id" => $conn->lastInsertId()]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to create location."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
