<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "--- Checking Latest Student ---\n";
$stmt = $conn->query("SELECT * FROM students ORDER BY id DESC LIMIT 1");
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student) {
    echo "Student ID: " . $student['id'] . "\n";
    echo "Name: " . $student['nama_siswa'] . "\n";
    echo "NISN: " . $student['nomor_induk'] . "\n";

    $stmtH = $conn->prepare("SELECT * FROM student_class_history WHERE student_id = ?");
    $stmtH->execute([$student['id']]);
    $history = $stmtH->fetch(PDO::FETCH_ASSOC);

    if ($history) {
        echo "History Found: Yes\n";
        echo "Class ID: " . $history['class_id'] . "\n";
        echo "Status: " . $history['status'] . "\n";
    } else {
        echo "History Found: NO (Error)\n";
    }
} else {
    echo "No students found.\n";
}
?>