<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();
$locations = $conn->query("SELECT id, name, latitude, longitude, radius_meter, is_active FROM locations")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($locations, JSON_PRETTY_PRINT);
