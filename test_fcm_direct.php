<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/fcm_helper.php';
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';

$db = new Database();
$conn = $db->getConnection();

$uid = $argv[1] ?? 158; // Default Luthfi
$stmt = $conn->prepare("SELECT fcm_token FROM employees WHERE id = ?");
$stmt->execute([$uid]);
$token = $stmt->fetchColumn();

if (!$token) {
    die("Token not found for user $uid\n");
}

$fcm = new FcmHelper();
$result = $fcm->sendNotification($token, "Test Notif", "Ini adalah pesan tes.", ["screen" => "test"]);

echo "Result: " . json_encode($result) . "\n";
?>
