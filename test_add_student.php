<?php
require_once 'config/database.php';

// Simulate POST Request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['nama_siswa'] = 'Test Student ' . time();
$_POST['nomor_induk'] = 'NISN' . time();
$_POST['tahun_ajaran'] = '2025/2026';
$_POST['class_id'] = 1; // Assuming class 1 exists
$_POST['status'] = 'Aktif';

// Mock function check_login to bypass auth
function check_login()
{
    return true;
}

// Header mock removed to avoid fatal error

// Include and run store.php
$cwd = getcwd();
chdir(__DIR__ . '/logic/students');
ob_start();
require 'store.php';
$output = ob_get_clean();
chdir($cwd);

echo "Output: " . $output . "\n";

// Verify Database
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SELECT * FROM students ORDER BY id DESC LIMIT 1");
$student = $stmt->fetch(PDO::FETCH_ASSOC);

echo "New Student: " . print_r($student, true) . "\n";

if ($student) {
    $stmtH = $conn->prepare("SELECT * FROM student_class_history WHERE student_id = ?");
    $stmtH->execute([$student['id']]);
    $history = $stmtH->fetch(PDO::FETCH_ASSOC);
    echo "History Record: " . print_r($history, true) . "\n";
}
?>