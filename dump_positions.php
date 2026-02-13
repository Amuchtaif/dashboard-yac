<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, name, level FROM positions ORDER BY level ASC");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('positions_dump.txt', print_r($data, true));
