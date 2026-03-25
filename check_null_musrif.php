<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, student_id FROM boarding_permits WHERE musrif_id IS NULL");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "NULL: " . $row['id'] . " Student: " . $row['student_id'] . "\n"; }
?>
