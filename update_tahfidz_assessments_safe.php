<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$check = $conn->query("SHOW COLUMNS FROM tahfidz_assessments LIKE 'assessment_type_id'");
if (!$check->fetch()) {
    $conn->exec("ALTER TABLE tahfidz_assessments ADD COLUMN assessment_type_id INT NULL AFTER category");
    echo "Column added.";
} else {
    echo "Column already exists.";
}

$conn->exec("
    UPDATE tahfidz_assessments a
    JOIN tahfidz_assessment_types t ON a.category = t.name
    SET a.assessment_type_id = t.id
    WHERE a.assessment_type_id IS NULL
");
echo " Data synced.";
