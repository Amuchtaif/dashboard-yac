<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("
    SELECT br.room_name, e.full_name, e.id as emp_id 
    FROM boarding_rooms br
    LEFT JOIN employees e ON br.supervisor_id = e.id
");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Room: " . $row['room_name'] . " - Supervisor: " . ($row['full_name'] ?? 'N/A') . " (ID: " . ($row['emp_id'] ?? 'N/A') . ")\n";
}
?>
