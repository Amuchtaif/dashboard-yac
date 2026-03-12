<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT COUNT(*) FROM tahfidz_assessment_types");
echo "Count: " . $stmt->fetchColumn();
