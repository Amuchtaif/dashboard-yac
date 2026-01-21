<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("DESCRIBE employees");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
?>