<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$tables = ['grade_levels', 'student_class_history', 'students'];
$results = [];

foreach ($tables as $table) {
    try {
        $stmt = $conn->query("DESCRIBE $table");
        $results[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $results[$table] = "Error: " . $e->getMessage();
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>