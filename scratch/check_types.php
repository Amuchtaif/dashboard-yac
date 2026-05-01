<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE tahfidz_assessment_types");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
