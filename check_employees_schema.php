<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE employees");
$output = "";
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $output .= "Field: {$row['Field']}, Type: {$row['Type']}\n";
}
file_put_contents('employees_schema.txt', $output);
echo "Employees schema logged.";
