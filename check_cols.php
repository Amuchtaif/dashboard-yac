<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();

function getCols($table) {
    global $conn;
    echo "\n--- $table ---\n";
    $stmt = $conn->query("SHOW COLUMNS FROM $table");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo $row['Field'] . ' | '; }
    echo "\n";
}

getCols('boarding_rooms');
getCols('boarding_room_members');
getCols('students');
?>
