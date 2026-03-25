<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$uid = $_GET['id'] ?? $argv[1] ?? 158;
$stmt = $conn->prepare("SELECT e.id, e.full_name, e.fcm_token, p.name as position_name FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
$stmt->execute([$uid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "ID: " . $row['id'] . " Name: " . $row['full_name'] . " Pos: " . $row['position_name'] . " FCM: " . ($row['fcm_token'] ? 'YES' : 'NONE') . "\n";
    if ($row['fcm_token']) echo "Token: " . $row['fcm_token'] . "\n";
} else {
    echo "User not found.\n";
}
?>
