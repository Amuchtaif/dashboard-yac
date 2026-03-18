<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    echo "--- boarding_rooms ---\n";
    $stmt = $conn->query("DESCRIBE boarding_rooms");
    while($row = $stmt->fetch()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n--- boarding_room_members ---\n";
    $stmt = $conn->query("DESCRIBE boarding_room_members");
    while($row = $stmt->fetch()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
