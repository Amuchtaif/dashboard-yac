<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
include_once '../config/db_mysqli.php';

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
