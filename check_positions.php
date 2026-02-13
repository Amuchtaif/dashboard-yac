<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT * FROM positions ORDER BY level ASC");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
