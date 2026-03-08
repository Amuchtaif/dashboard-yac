<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$tables = ['class_schedules', 'education_units', 'grade_levels', 'subjects'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    try {
        $res = $conn->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        print_r($res);
    } catch (Exception $e) { echo $e->getMessage()."\n"; }
}
?>
