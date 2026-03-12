<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$tables = ['students', 'employees', 'student_assessments', 'student_assessment_details'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    try {
        $stmt = $conn->query("DESCRIBE $table");
        print_r($stmt->fetchAll());
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
