<?php
require_once 'config/db_mysqli.php';

// Check attendance_pengampu schema
$result = $mysqli->query("DESCRIBE attendance_pengampu");
if ($result) {
    echo "Columns in attendance_pengampu:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\n";
    }
} else {
    echo "Query Failed: " . $mysqli->error . "\n";
}

// Check Positions for "Koordinator"
$result = $mysqli->query("SELECT id, position_name FROM positions WHERE position_name LIKE '%Koordinator%' OR position_name LIKE '%pengampu%'");
if ($result) {
    echo "\nPositions:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['id'] . ": " . $row['position_name'] . "\n";
    }
} else {
    echo "Query Failed: " . $mysqli->error . "\n";
}
?>
