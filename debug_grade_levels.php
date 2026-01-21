<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE grade_levels");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Columns in 'grade_levels' table:\n";
foreach ($columns as $col) {
    echo "- " . $col . "\n";
}
?>