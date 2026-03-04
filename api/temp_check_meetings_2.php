<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_db";

$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $mysqli->connect_error]));
}

try {
    $tables = ['meetings', 'meeting_participants'];
    $result = [];
    foreach ($tables as $table) {
        $q = $mysqli->query("DESCRIBE $table");
        if ($q) {
            $cols = [];
            while ($row = $q->fetch_assoc()) {
                $cols[] = $row;
            }
            $result[$table] = $cols;
        } else {
            $result[$table] = "Error: " . $mysqli->error;
        }
    }
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
$mysqli->close();
?>
