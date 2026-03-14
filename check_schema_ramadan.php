<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE office_settings");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ': ' . $row['Type'] . "\n";
}
echo "--- UNITS ---\n";
$stmt = $conn->query("DESCRIBE units");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ': ' . $row['Type'] . "\n";
}
