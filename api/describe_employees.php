<?php
require_once 'config/db_mysqli.php';
$res = $mysqli->query("DESCRIBE employees");
while($row = $res->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";
?>
