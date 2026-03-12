<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$tables = ['students', 'employees', 'student_assessments', 'student_assessment_details'];
$output = "";
foreach ($tables as $table) {
    $output .= "--- $table ---\n";
    try {
        $stmt = $conn->query("DESCRIBE $table");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $output .= $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } catch (Exception $e) {
        $output .= "Error: " . $e->getMessage() . "\n";
    }
    $output .= "\n";
}
file_put_contents('schema_info.txt', $output);
echo "Schema written to schema_info.txt\n";
?>
