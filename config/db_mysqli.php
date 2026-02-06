<?php
// config/db_mysqli.php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_db";

$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_error) {
    header("Content-Type: application/json; charset=UTF-8");
    die(json_encode(["success" => false, "message" => "Connection failed: " . $mysqli->connect_error]));
}

$mysqli->set_charset("utf8");
?>