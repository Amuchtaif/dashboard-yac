<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$tables = ['student_assessments', 'student_assessment_details', 'subjects', 'grade_levels', 'assessment_types'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $stmt = $conn->query("DESCRIBE $table");
    print_r($stmt->fetchAll());
}
?>
