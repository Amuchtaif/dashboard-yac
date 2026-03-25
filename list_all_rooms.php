<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, room_name, supervisor_id FROM boarding_rooms");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Room " . $row['id'] . ": " . $row['room_name'] . " - Supervisor ID: " . $row['supervisor_id'] . "\n";
}
?>
