<?php
// api/inventory/items/delete.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, POST");
require_once __DIR__ . '/../../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id ?? $_GET['id'] ?? $_POST['id'] ?? null;

    if (!$id) {
        echo json_encode(["success" => false, "message" => "Item ID is required."]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(["success" => true, "message" => "Item deleted successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to delete item."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
