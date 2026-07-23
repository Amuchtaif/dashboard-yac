<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $cols = $conn->query("SHOW COLUMNS FROM attendances LIKE 'location_id_out'")->fetchAll();
    if (empty($cols)) {
        $conn->exec("ALTER TABLE attendances ADD COLUMN location_id_out INT(11) NULL AFTER location_id");
        echo "Column location_id_out added successfully.\n";
    } else {
        echo "Column location_id_out already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
