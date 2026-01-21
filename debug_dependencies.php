<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Check Grade Levels ---\n";
$stmt = $conn->query("SELECT * FROM grade_levels LIMIT 5");
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($grades);

echo "--- Check Academic Years ---\n";
try {
    $stmt = $conn->query("SELECT * FROM academic_years");
    $years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($years);
} catch (PDOException $e) {
    echo "Error fetching years: " . $e->getMessage() . "\n";
}
?>