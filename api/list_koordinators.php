<?php
require_once 'config/db_mysqli.php';
$query = "SELECT e.id, e.full_name FROM employees e JOIN positions p ON e.position_id = p.id WHERE p.position_name LIKE '%Koordinator Tahfidz%' AND e.status = 'active'";
$res = $mysqli->query($query);
while($row = $res->fetch_assoc()) echo "ID: {$row['id']} | Name: {$row['full_name']}\n";
?>
