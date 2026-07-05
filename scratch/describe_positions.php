<?php
require_once __DIR__ . '/../config/db_mysqli.php';
$res = $mysqli->query('DESCRIBE positions');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}
