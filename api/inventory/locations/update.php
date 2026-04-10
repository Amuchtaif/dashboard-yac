<?php
// api/inventory/locations/update.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
require_once __DIR__ . '/../../../config/database.php';

// Helper Function: Mencegah Circular Parent
function isCircularDependency($conn, $targetId, $newParentId) {
    if ($targetId == $newParentId) return true; 

    $currentCursor = $newParentId;
    while ($currentCursor != null) {
        if ($currentCursor == $targetId) {
            return true; // Ditemukan loop
        }
        $stmt = $conn->prepare("SELECT parent_id FROM inventory_locations WHERE id = ?");
        $stmt->execute([$currentCursor]);
        $currentCursor = $stmt->fetchColumn(); 
    }
    
    return false;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id ?? $_GET['id'] ?? null;
    $name = $data->name ?? '';
    $parent_id = $data->parent_id ?? null;
    $label = $data->label ?? $data->location_label ?? $name;

    if (!$id || empty($name)) {
        echo json_encode(["success" => false, "message" => "ID and Name are required."]);
        exit;
    }

    if ($parent_id === "") $parent_id = null;

    // Check Circular
    if ($parent_id !== null && isCircularDependency($conn, $id, $parent_id)) {
        echo json_encode(["success" => false, "message" => "Invalid parent selection! It creates an infinite circular loop."]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE inventory_locations SET name = ?, location_label = ?, parent_id = ? WHERE id = ?");
    if ($stmt->execute([$name, $label, $parent_id, $id])) {
        echo json_encode(["success" => true, "message" => "Location updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update location."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
