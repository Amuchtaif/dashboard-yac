<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE students");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Columns in 'students' table:\n";
foreach ($columns as $col) {
    echo "- " . $col . "\n";
}
?>