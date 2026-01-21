<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT * FROM divisions");
$divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($divisions, JSON_PRETTY_PRINT);
?>