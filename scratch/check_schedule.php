<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "Active Academic Year:\n";
$stmt = $conn->query("SELECT * FROM academic_years WHERE is_active = 1");
print_r($stmt->fetchAll());

echo "\nAcademic Year ID 2:\n";
$stmt = $conn->query("SELECT * FROM academic_years WHERE id = 2");
print_r($stmt->fetchAll());

echo "\nSchedules for Grade 9B (if id=18, checking via name):\n";
$stmt = $conn->query("
    SELECT cs.*, s.name as subject_name 
    FROM class_schedules cs 
    JOIN grade_levels gl ON cs.grade_level_id = gl.id 
    JOIN subjects s ON cs.subject_id = s.id
    WHERE gl.name LIKE '%9B%'
");
print_r($stmt->fetchAll());
