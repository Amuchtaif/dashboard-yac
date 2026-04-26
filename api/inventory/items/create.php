<?php
// api/inventory/items/create.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/app.php'; // Use global helpers from app.php

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $isJson = (strpos($_SERVER["CONTENT_TYPE"] ?? '', "application/json") !== false);
    if ($isJson) {
        $data = json_decode(file_get_contents("php://input"));
        $name = $data->name ?? '';
        $code = $data->item_code ?? '';
        $location_id = $data->location_id ?? null;
        $qty = $data->qty ?? 0;
        $unit = $data->item_unit ?? '';
        $desc = $data->description ?? '';
        $condition = $data->item_condition ?? 'Baik';
        $purchase_date = $data->purchase_date ?? null;
    } else {
        $name = $_POST['name'] ?? '';
        $code = $_POST['item_code'] ?? '';
        $location_id = $_POST['location_id'] ?? null;
        $qty = $_POST['qty'] ?? 0;
        $unit = $_POST['item_unit'] ?? '';
        $desc = $_POST['description'] ?? '';
        $condition = $_POST['item_condition'] ?? 'Baik';
        $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
    }

    if (empty($name) || !$location_id) {
        echo json_encode(["success" => false, "message" => "Name and Location are required."]);
        exit;
    }

    $photo = null;
    if (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../../uploads/inventory/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = uniqid() . '.' . pathinfo($_FILES['item_photo']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['item_photo']['tmp_name'], $uploadDir . $fileName)) $photo = $fileName;
    }

    // Insert
    $stmt = $conn->prepare("INSERT INTO inventory_items (item_code, name, location_id, qty, item_unit, description, item_condition, item_photo, purchase_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$code, $name, $location_id, (int)$qty, $unit, $desc, $condition, $photo, $purchase_date])) {
        $lastId = $conn->lastInsertId();
        
        // AUTO-GEN CODE V2
        if (empty($code)) {
            $locStmt = $conn->prepare("SELECT name FROM inventory_locations WHERE id = ?");
            $locStmt->execute([$location_id]);
            $locName = $locStmt->fetchColumn();

            $finalCode = generateItemCodeV2($conn, $location_id, $locName, $name, $lastId);
            $conn->prepare("UPDATE inventory_items SET item_code = ? WHERE id = ?")->execute([$finalCode, $lastId]);
        }

        echo json_encode(["success" => true, "message" => "Item added successfully.", "id" => $lastId]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to add item."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
