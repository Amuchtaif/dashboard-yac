<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Sample student_assessment_details ---\n";
$stmt = $conn->query("SELECT * FROM student_assessment_details LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Sample students ---\n";
$stmt = $conn->query("SELECT id, nama_siswa, nomor_induk FROM students LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Sample student_assessments ---\n";
$stmt = $conn->query("SELECT * FROM student_assessments LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
