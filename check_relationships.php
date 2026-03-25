<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- boarding_rooms ---\n";
$stmt = $conn->query("DESCRIBE boarding_rooms");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo $row['Field'] . ' | ' . $row['Type'] . "\n"; }

echo "\n--- students ---\n";
$stmt = $conn->query("DESCRIBE students");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo $row['Field'] . ' | ' . $row['Type'] . "\n"; }

echo "\n--- boarding_room_members ---\n";
$stmt = $conn->query("DESCRIBE boarding_room_members");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo $row['Field'] . ' | ' . $row['Type'] . "\n"; }
?>
