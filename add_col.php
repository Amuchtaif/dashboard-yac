<?php
require 'config/database.php';
try {
    $db = new Database();
    $c = $db->getConnection();
    $c->exec("ALTER TABLE inventory_items ADD COLUMN item_condition ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') DEFAULT 'Baik' AFTER qty;");
    echo 'Added item_condition successfully';
} catch (Exception $e) {
    echo $e->getMessage();
}
