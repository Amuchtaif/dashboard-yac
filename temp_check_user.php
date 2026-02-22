<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT user_id, COUNT(*) as count FROM attendances GROUP BY user_id ORDER BY count DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($row);
?>
