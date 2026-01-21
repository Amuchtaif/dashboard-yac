<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("DESCRIBE academic_years");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "START_JSON\n";
echo json_encode($columns, JSON_PRETTY_PRINT);
echo "\nEND_JSON\n";
?>