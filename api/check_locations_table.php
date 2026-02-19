<?php
include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("DESCRIBE locations");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($columns, JSON_PRETTY_PRINT);
?>
