<?php
require_once 'config/db_mysqli.php';
$res = $mysqli->query("SHOW TABLES");
while($row = $res->fetch_row()) {
    if (stripos($row[0], 'position') !== false) echo "Found: " . $row[0] . "\n";
}
?>
