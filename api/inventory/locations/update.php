<?php
// api/inventory/locations/update.php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/app.php';

// Helper Function: Mencegah Circular Parent
function isCircularDependency($conn, $targetId, $newParentId) {
    if ($targetId == $newParentId) return true; 

    $currentCursor = $newParentId;
    while ($currentCursor) {
        if ($currentCursor == $targetId) {
            return true; 
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
    
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    $id = $data['id'] ?? $_GET['id'] ?? $_POST['id'] ?? null;
    $name = $data['name'] ?? $_POST['name'] ?? '';
    $parent_id = $data['parent_id'] ?? $_POST['parent_id'] ?? null;
    $label = $data['label'] ?? $data['location_label'] ?? $_POST['location_label'] ?? $name;
    $location_code = $data['location_code'] ?? $_POST['location_code'] ?? null;

    if (!$id || empty($name)) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "ID dan Nama wajib diisi."]);
        exit;
    }

    if ($parent_id === "" || $parent_id === "null" || $parent_id === 0 || $parent_id === "0") {
        $parent_id = null;
    }

    // Check Circular
    if ($parent_id !== null && isCircularDependency($conn, $id, $parent_id)) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Lokasi induk tidak valid (menyebabkan loop melingkar)."]);
        exit;
    }

    // Check uniqueness if code is changed
    if ($location_code) {
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM inventory_locations WHERE location_code = ? AND id != ?");
        $checkStmt->execute([$location_code, $id]);
        if ($checkStmt->fetchColumn() > 0) {
            ob_clean();
            echo json_encode(["success" => false, "message" => "Kode lokasi sudah digunakan oleh lokasi lain."]);
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE inventory_locations SET name = ?, location_label = ?, location_code = ?, parent_id = ? WHERE id = ?");
    if ($stmt->execute([$name, $label, $location_code, $parent_id, $id])) {
        ob_clean();
        echo json_encode(["success" => true, "message" => "Lokasi berhasil diupdate."]);
    } else {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Gagal mengupdate database."]);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(["success" => false, "message" => "System Error: " . $e->getMessage()]);
}
?>
