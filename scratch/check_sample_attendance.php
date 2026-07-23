<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();
$sample = $conn->query("
    SELECT a.id, a.user_id, a.location_id, a.lat_in, a.long_in, a.lat_out, a.long_out, l.latitude as loc_lat, l.longitude as loc_long, l.radius_meter
    FROM attendances a
    LEFT JOIN locations l ON a.location_id = l.id
    ORDER BY a.id DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($sample, JSON_PRETTY_PRINT);
