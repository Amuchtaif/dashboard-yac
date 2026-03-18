<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW TABLES LIKE 'boarding_attendance%'");
foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
    echo $t . "\n";
}
