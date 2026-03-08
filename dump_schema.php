<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$tables = ['class_schedules', 'education_units', 'grade_levels', 'subjects', 'rpp'];
$log = "";
foreach ($tables as $t) {
    try {
        $res = $conn->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        $log .= "Table: $t\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Exception $e) { $log .= "Table: $t Error: " . $e->getMessage() . "\n"; }
}
file_put_contents('schema_dump.txt', $log);
echo "SUCCESS";
?>
