<?php
require_once 'config/db_mysqli.php';
$res = $mysqli->query("SHOW TABLES");
while($row = $res->fetch_row()) echo $row[0] . "\n";
?>