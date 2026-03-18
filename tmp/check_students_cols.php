<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE students");
foreach($stmt->fetchAll() as $row) {
    echo $row['Field'] . "\n";
}
