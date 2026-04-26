<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $conn->exec("ALTER TABLE inventory_items ADD COLUMN purchase_date DATE AFTER item_unit");
    echo "Column purchase_date added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
