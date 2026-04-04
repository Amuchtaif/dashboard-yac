<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "--- Testing api/boarding/get_students.php ---\n";
$_GET['room_id'] = 3;
$_GET['date'] = date('Y-m-d');

try {
    require 'api/boarding/get_students.php';
} catch (Exception $e) {
    echo "\nCaught Exception: " . $e->getMessage() . "\n";
}
?>
