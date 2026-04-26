<?php
// api/inventory/locations/create.php
ob_start(); // Buffer output to prevent notices from breaking JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/app.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Read JSON input
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    // Fallback to $_POST if JSON is empty
    $name = $data['name'] ?? $_POST['name'] ?? '';
    $parent_id = $data['parent_id'] ?? $_POST['parent_id'] ?? null;
    $label = $data['label'] ?? $data['location_label'] ?? $_POST['location_label'] ?? $name;
    $location_code = $data['location_code'] ?? $_POST['location_code'] ?? null;

    if (empty($name)) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Nama lokasi wajib diisi."]);
        exit;
    }

    if ($parent_id === "" || $parent_id === "null" || $parent_id === 0 || $parent_id === "0") {
        $parent_id = null;
    }

    // Auto-generate Code if not provided
    if (empty($location_code) || $location_code === 'Otomatis') {
        $parentCode = null;
        if ($parent_id) {
            $pStmt = $conn->prepare("SELECT location_code FROM inventory_locations WHERE id = ?");
            $pStmt->execute([$parent_id]);
            $parentCode = $pStmt->fetchColumn();
        }
        $location_code = generateLocationCode($name, $parentCode);
    }

    // Ensure Unique Code
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM inventory_locations WHERE location_code = ?");
    $checkStmt->execute([$location_code]);
    if ($checkStmt->fetchColumn() > 0) {
        $location_code .= rand(10, 99);
    }

    $stmt = $conn->prepare("INSERT INTO inventory_locations (name, location_code, location_label, parent_id) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$name, $location_code, $label, $parent_id])) {
        ob_clean();
        echo json_encode(["success" => true, "message" => "Lokasi berhasil dibuat.", "id" => $conn->lastInsertId(), "code" => $location_code]);
    } else {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Gagal menyimpan ke database."]);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(["success" => false, "message" => "System Error: " . $e->getMessage()]);
}
?>
