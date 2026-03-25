<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, full_name, fcm_token FROM employees WHERE position_id = 1");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "ID: " . $row['id'] . " Name: " . $row['full_name'] . " FCM: " . ($row['fcm_token'] ? 'YES' : 'NONE') . "\n"; }
?>
