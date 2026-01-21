<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // Add grade_levels column
    $sql1 = "ALTER TABLE education_units ADD COLUMN grade_levels VARCHAR(255) NULL AFTER description";
    $conn->exec($sql1);
    echo "Column 'grade_levels' added successfully.<br>";
} catch (PDOException $e) {
    echo "Error adding 'grade_levels': " . $e->getMessage() . "<br>";
}

try {
    // Add operational_unit_id column
    $sql2 = "ALTER TABLE education_units ADD COLUMN operational_unit_id INT NULL AFTER grade_levels";
    $conn->exec($sql2);
    echo "Column 'operational_unit_id' added successfully.<br>";
} catch (PDOException $e) {
    echo "Error adding 'operational_unit_id': " . $e->getMessage() . "<br>";
}
?>