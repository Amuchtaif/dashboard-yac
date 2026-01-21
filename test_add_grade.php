<?php
// Mock Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;

// Redirect mock removed to avoid conflict with app.php

// Mock POST data (Missing 'level')
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['name'] = 'Test Class ' . time();
$_POST['education_unit_id'] = 1; // Assuming 1 exists
$_POST['teacher_id'] = '';
$_POST['capacity'] = 30;
// 'level' is intentionally OMITTED

// Capture redirect output
$cwd = getcwd();
chdir(__DIR__ . '/logic/grade_levels');

register_shutdown_function(function () {
    // Check DB
    require_once '../../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->query("SELECT * FROM grade_levels ORDER BY id DESC LIMIT 1");
    $grade = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n--- Shutdown Verification ---\n";
    if ($grade) {
        echo "Latest Grade: " . $grade['name'] . "\n";
        echo "Level: " . $grade['level'] . "\n"; // Should be '-'
    } else {
        echo "No Grade Found\n";
    }
});

ob_start();
require 'store.php';
$output = ob_get_clean();
chdir($cwd);

echo "Output: " . $output . "\n";
?>