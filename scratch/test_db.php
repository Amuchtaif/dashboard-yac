<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db_mysqli.php';

echo "=== DIAGNOSING tahfidz_memorization columns ===\n";
$desc_memo = $mysqli->query("DESCRIBE tahfidz_memorization");
if ($desc_memo) {
    while ($row = $desc_memo->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error describing tahfidz_memorization: " . $mysqli->error . "\n";
}

echo "\n=== DIAGNOSING students columns ===\n";
$desc_students = $mysqli->query("DESCRIBE students");
if ($desc_students) {
    while ($row = $desc_students->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error describing students: " . $mysqli->error . "\n";
}
