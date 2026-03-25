<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT musrif_id FROM boarding_permits WHERE id = 9");
echo "ID 9 Musrif ID: " . $stmt->fetchColumn() . "\n";
?>
