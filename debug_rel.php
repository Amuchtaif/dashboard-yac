<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$res = $conn->query("SELECT cs.id, cs.academic_year_id, cs.grade_level_id, cs.employee_id, cs.subject_id FROM class_schedules cs LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

$res2 = $conn->query("SELECT id FROM academic_years")->fetchAll(PDO::FETCH_COLUMN);
echo "Academic Years: " . implode(', ', $res2) . "\n";
