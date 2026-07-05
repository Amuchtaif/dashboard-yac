<?php
require_once __DIR__ . '/../config/db_mysqli.php';

function inspect($table) {
    global $mysqli;
    echo "=== DESCRIBE $table ===\n";
    $desc = $mysqli->query("DESCRIBE `$table`");
    if ($desc) {
        while ($row = $desc->fetch_assoc()) {
            print_r($row);
        }
    } else {
        echo "Error describing $table: " . $mysqli->error . "\n";
    }
}

inspect('academic_years');
inspect('target_hafalan');
