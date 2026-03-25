<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("
    SELECT COUNT(*) 
    FROM boarding_permits bp
    LEFT JOIN boarding_room_members brm ON bp.student_id = brm.student_id
    WHERE brm.room_id IS NULL
");
echo "Permits for students with NO room: " . $stmt->fetchColumn() . "\n";
?>
