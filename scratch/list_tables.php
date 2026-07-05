<?php
require_once __DIR__ . '/../config/db_mysqli.php';
$res = $mysqli->query('SHOW TABLES');
while($r = $res->fetch_row()) {
    echo $r[0] . PHP_EOL;
}
