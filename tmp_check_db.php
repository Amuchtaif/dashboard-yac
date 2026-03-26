<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE attendances");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
