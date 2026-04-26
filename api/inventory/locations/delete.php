<?php
// api/inventory/locations/delete.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    $id = ($data && property_exists($data, 'id')) ? $data->id : ($_GET['id'] ?? $_POST['id'] ?? null);

    if (!$id) {
        echo json_encode(["success" => false, "message" => "Location ID is required."]);
        exit;
    }

    // Since we have ON DELETE CASCADE for parent_id, deleting this will recursively delete child locations.
    // However, ON DELETE RESTRICT on items location_id will prevent deletion if items exist here.
    
    // Safety check: Are there items linked to this location or any child locations?
    // Doing a recursive CTE or just letting MySQL constraint fail are both ok.
    // Let's rely on MySQL RESTRICT constraint for items.

    $stmt = $conn->prepare("DELETE FROM inventory_locations WHERE id = ?");
    if ($stmt->execute([$id])) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Location and all its sub-locations deleted."]);
        } else {
            echo json_encode(["success" => false, "message" => "Location not found."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Failed to delete location. It may be linked to some items."]);
    }
} catch (Throwable $e) {
    if ($e instanceof PDOException && isset($e->errorInfo) && $e->errorInfo[1] == 1451) {
        echo json_encode(["success" => false, "message" => "Cannot delete location because there are items stored in it or its sub-locations."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
}
?>
