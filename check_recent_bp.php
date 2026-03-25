<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, student_id, musrif_id, status FROM boarding_permits ORDER BY id DESC LIMIT 5");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "ID: " . $row['id'] . " Student: " . $row['student_id'] . " Musrif: " . $row['musrif_id'] . " Status: " . $row['status'] . "\n"; }
?>
