<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE tahfidz_assessments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
