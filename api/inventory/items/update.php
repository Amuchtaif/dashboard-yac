<?php
// api/inventory/items/update.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT"); // Support POST for multipart uploads
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/app.php'; // Use global helpers from app.php

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $isJson = (strpos($_SERVER["CONTENT_TYPE"] ?? '', "application/json") !== false);
    if ($isJson) {
        $data = json_decode(file_get_contents("php://input"));
        $id = $data->id ?? $_GET['id'] ?? null;
        $name = $data->name ?? '';
        $code = $data->item_code ?? '';
        $location_id = $data->location_id ?? null;
        $qty = $data->qty ?? 0;
        $unit = $data->item_unit ?? '';
        $desc = $data->description ?? '';
        $condition = $data->item_condition ?? 'Baik';
    } else {
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        $name = $_POST['name'] ?? '';
        $code = $_POST['item_code'] ?? '';
        $location_id = $_POST['location_id'] ?? null;
        $qty = $_POST['qty'] ?? 0;
        $unit = $_POST['item_unit'] ?? '';
        $desc = $_POST['description'] ?? '';
        $condition = $_POST['item_condition'] ?? 'Baik';
    }

    if (!$id || empty($name) || !$location_id) {
        echo json_encode(["success" => false, "message" => "ID, Name, and Location are required."]);
        exit;
    }

    // AUTO-GEN CODE V2
    if (empty($code)) {
        $locStmt = $conn->prepare("SELECT name FROM inventory_locations WHERE id = ?");
        $locStmt->execute([$location_id]);
        $locName = $locStmt->fetchColumn();

        $code = generateItemCodeV2($conn, $location_id, $locName, $name, $id);
    }

    $sql = "UPDATE inventory_items SET item_code = ?, name = ?, location_id = ?, qty = ?, item_unit = ?, description = ?, item_condition = ?";
    $params = [$code, $name, $location_id, (int)$qty, $unit, $desc, $condition];

    if (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../../uploads/inventory/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = uniqid() . '.' . pathinfo($_FILES['item_photo']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['item_photo']['tmp_name'], $uploadDir . $fileName)) {
            $prev = $conn->prepare("SELECT item_photo FROM inventory_items WHERE id = ?");
            $prev->execute([$id]);
            $old = $prev->fetch();
            if ($old && $old['item_photo'] && file_exists($uploadDir . $old['item_photo'])) unlink($uploadDir . $old['item_photo']);
            $sql .= ", item_photo = ?";
            $params[] = $fileName;
        }
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;

    $stmt = $conn->prepare($sql);
    if ($stmt->execute($params)) {
        echo json_encode(["success" => true, "message" => "Item updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update item."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
