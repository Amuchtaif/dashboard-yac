<?php
require_once __DIR__ . '/../config/db_mysqli.php';
$res = $mysqli->query('SELECT COUNT(*) FROM tahfidz_memorization');
if ($res) {
    $row = $res->fetch_row();
    echo "tahfidz_memorization count: " . $row[0] . PHP_EOL;
} else {
    echo "No table tahfidz_memorization or error: " . $mysqli->error . PHP_EOL;
}
$res = $mysqli->query('SELECT COUNT(*) FROM students');
if ($res) {
    $row = $res->fetch_row();
    echo "students count: " . $row[0] . PHP_EOL;
}
