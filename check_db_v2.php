<?php
// fix error reporting
mysqli_report(MYSQLI_REPORT_OFF);

require_once 'config/db_mysqli.php';

if ($mysqli->connect_errno) {
    die("Connection failed: " . $mysqli->connect_error);
}

// 1. Check Tables
echo "Tables:\n";
$tables = $mysqli->query("SHOW TABLES");
$found_attendance_pengampu = false;
$found_tahfidz_teacher_attendance = false;

if ($tables) {
    while ($row = $tables->fetch_array()) {
        echo $row[0] . "\n";
        if ($row[0] == 'attendance_pengampu') $found_attendance_pengampu = true;
        if ($row[0] == 'tahfidz_teacher_attendance') $found_tahfidz_teacher_attendance = true;
    }
}

// 2. Check Schemas
if ($found_attendance_pengampu) {
    echo "\nSchema of attendance_pengampu:\n";
    $res = $mysqli->query("DESCRIBE attendance_pengampu");
    while ($r = $res->fetch_assoc()) echo $r['Field'] . " " . $r['Type'] . "\n";
}

if ($found_tahfidz_teacher_attendance) {
    echo "\nSchema of tahfidz_teacher_attendance:\n";
    $res = $mysqli->query("DESCRIBE tahfidz_teacher_attendance");
    while ($r = $res->fetch_assoc()) echo $r['Field'] . " " . $r['Type'] . "\n";
}

// 3. Check Positions
echo "\nPositions:\n";
$pos = $mysqli->query("SELECT id, position_name FROM positions WHERE position_name LIKE '%Koordinator%' OR position_name LIKE '%Pengampu%'");
if ($pos) {
    while ($r = $pos->fetch_assoc()) {
        echo $r['id'] . ": " . $r['position_name'] . "\n";
    }
}
?>
