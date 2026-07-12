<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$tables = ['students', 'grade_levels', 'student_class_history'];
foreach ($tables as $t) {
    echo "=== Table: $t ===\n";
    $res = $conn->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($res as $row) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "\n";
}
