<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "--- Testing api/boarding/get_rooms.php ---\n";
// Simulate GET parameters
$_GET['date'] = date('Y-m-d');
// $_GET['supervisor_id'] = 158; // Try with and without

try {
    require 'api/boarding/get_rooms.php';
} catch (Exception $e) {
    echo "\nCaught Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
