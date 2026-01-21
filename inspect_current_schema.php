<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$tables = ['students', 'grade_levels', 'student_class_history'];

foreach ($tables as $table) {
    echo "--- $table TABLE ---\n";
    try {
        $stmt = $conn->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo $col['Field'] . " | " . $col['Type'] . "\n";
        }
    } catch (PDOException $e) {
        echo "Error describing $table: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
?>