<?php
require_once __DIR__ . '/../config/db_mysqli.php';
$tables = ['grade_levels', 'class_schedules'];
foreach ($tables as $t) {
    echo "=== Table: $t ===\n";
    $res = $mysqli->query("DESCRIBE `$t`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . "\n";
        }
    } else {
        echo "Error: " . $mysqli->error . "\n";
    }
}
