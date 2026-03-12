<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$sql = "ALTER TABLE tahfidz_assessments ADD COLUMN IF NOT EXISTS assessment_type_id INT NULL AFTER category";
$conn->exec($sql);

// Optional: populate assessment_type_id based on category string match
$conn->exec("
    UPDATE tahfidz_assessments a
    JOIN tahfidz_assessment_types t ON a.category = t.name
    SET a.assessment_type_id = t.id
    WHERE a.assessment_type_id IS NULL
");

echo "Column assessment_type_id added to tahfidz_assessments.";
