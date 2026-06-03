<?php
require_once 'config/db_mysqli.php';
$pos_name_col = 'name';
$checkCol = $mysqli->query("SHOW COLUMNS FROM positions LIKE 'position_name'");
if ($checkCol && $checkCol->num_rows > 0) {
    $pos_name_col = 'position_name';
}

$query = "SELECT e.id, e.full_name FROM employees e JOIN positions p ON e.position_id = p.id WHERE p.{$pos_name_col} LIKE '%Koordinator Tahfidz%' AND e.status = 'active'";
$res = $mysqli->query($query);
while($row = $res->fetch_assoc()) echo "ID: {$row['id']} | Name: {$row['full_name']}\n";
?>
