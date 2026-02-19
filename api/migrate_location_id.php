<?php
include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
try {
    $db->exec("ALTER TABLE attendances ADD COLUMN location_id INT NULL AFTER user_id");
    echo "Successfully added location_id to attendances table";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
