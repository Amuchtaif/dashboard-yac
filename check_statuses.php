<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query('SELECT status, COUNT(*) FROM students GROUP BY status');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
