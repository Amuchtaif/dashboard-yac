<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT DISTINCT approved_by FROM boarding_permits WHERE approved_by IS NOT NULL");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo "Approved by: " . $row['approved_by'] . "\n"; }
?>
