<?php
mysqli_report(MYSQLI_REPORT_ALL);
require 'config/db_mysqli.php';

echo "Database: " . $dbname . "\n";
echo "--- Tables ---\n";
$res = $mysqli->query("SHOW TABLES");
$tables = [];
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
    $tables[] = $row[0];
}

echo "\n--- Positions Like 'Koordinator' ---\n";
$res = $mysqli->query("SELECT * FROM positions WHERE position_name LIKE '%Koordinator%' OR position_name LIKE '%Tahfidz%'");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
