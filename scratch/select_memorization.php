<?php
require_once __DIR__ . '/../config/db_mysqli.php';
$res = $mysqli->query('SELECT * FROM tahfidz_memorization LIMIT 5');
while($row = $res->fetch_assoc()) {
    print_r($row);
}
