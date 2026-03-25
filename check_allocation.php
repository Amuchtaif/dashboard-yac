<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Lutfi --- \n";
$stmt = $conn->query("SELECT id, name FROM employees WHERE name LIKE '%Lutfi%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
    echo "ID: " . $row['id'] . " Name: " . $row['name'] . "\n";
    $rooms = $conn->query("SELECT id, room_name, supervisor_id FROM boarding_rooms WHERE supervisor_id = " . $row['id']);
    while($r = $rooms->fetch(PDO::FETCH_ASSOC)) {
        echo "  Room ID: " . $r['id'] . " Room: " . $r['room_name'] . "\n";
    }
}

echo "\n--- Amr --- \n";
$stmt = $conn->query("SELECT id, name FROM employees WHERE name LIKE '%Amr%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
    echo "ID: " . $row['id'] . " Name: " . $row['name'] . "\n";
    $rooms = $conn->query("SELECT id, room_name, supervisor_id FROM boarding_rooms WHERE supervisor_id = " . $row['id']);
    while($r = $rooms->fetch(PDO::FETCH_ASSOC)) {
        echo "  Room ID: " . $r['id'] . " Room: " . $r['room_name'] . "\n";
    }
}
?>
