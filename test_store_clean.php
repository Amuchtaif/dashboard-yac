<?php
// Define constant to prevent app.php from running fully if needed, or just include DB
define('TEST_MODE', true);

require_once 'config/database.php';

// Mock Session
session_start();
$_SESSION['user_id'] = 1;

// Removed check_login/header mocks to avoid re-declaration errors.
// app.php will define them.

// Prepare POST data
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['nama_siswa'] = 'Test Student ' . time();
$_POST['nomor_induk'] = 'NISN' . time();
$_POST['tahun_ajaran'] = '2025/2026';
$_POST['class_id'] = 81; // Valid ID based on debugging
$_POST['status'] = 'Aktif';

// Copy-paste logic from store.php effectively, or include it?
// Including store.php causes issues with app.php being included twice in a real env vs test.
// store.php has: require_once '../../config/app.php';
// We should rely on `require_once` preventing double inclusion.
// The issue is `app.php` defines functions like `url()` which might conflict if my test script defined them?
// No, I didn't define `url()`.
// The fatal error was: "Cannot redeclare ... in app.php". This means APP.PHP was included TWICE.
// Once by test script? No.
// Inspect store.php: line 2: require_once '../../config/database.php'; line 3: require_once '../../config/app.php';
// My test script does: require 'store.php'.
// If I enable standard require_once, it should be fine.
// The previous error might be because I included `store.php` which includes `app.php`. 
// If `test_add_student.php` didn't include `app.php`, then `store.php` includes it. 
// Ah, `app.php` might contain code that executes immediately?
// Let's TRY to just include logic/students/store.php but carefully.

// Let's create a wrapper that simulates the environment perfectly.
chdir('d:/xampp/htdocs/dashboard-yac/logic/students/');

// We simply require store.php. 
// We need to catch the "exit()" call.
// We can use register_shutdown_function to check DB after exit.

register_shutdown_function(function () {
    echo "\n--- Shutdown Check ---\n";
    // global $conn; // DB might be closed?
    // Re-connect
    require_once '../../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT * FROM students ORDER BY id DESC LIMIT 1");
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        echo "Last Student: " . $student['nama_siswa'] . "\n";
        // Check History
        $stmtH = $conn->prepare("SELECT * FROM student_class_history WHERE student_id = ?");
        $stmtH->execute([$student['id']]);
        $history = $stmtH->fetch(PDO::FETCH_ASSOC);
        echo "History: " . ($history ? "FOUND (Class " . $history['class_id'] . ")" : "NOT FOUND") . "\n";
    }
});

// Run it
require 'store.php';

?>