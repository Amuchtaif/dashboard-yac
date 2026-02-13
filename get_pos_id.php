<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SELECT id, name FROM positions WHERE name LIKE '%Koordinator Tahfidz%'");
$pos = $stmt->fetch(PDO::FETCH_ASSOC);
echo "POSITION_ID: " . ($pos ? $pos['id'] : 'NOT_FOUND') . "\n";
echo "POSITION_NAME: " . ($pos ? $pos['name'] : 'NOT_FOUND') . "\n";
