<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SELECT id, name FROM units WHERE division_id = 2");
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($all as $row) {
    echo $row['id'] . ":" . $row['name'] . "\n";
}
