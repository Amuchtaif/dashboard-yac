<?php
require_once 'config/database.php';
try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "EMPLOYEES: ";
    $stmt = $conn->query("DESCRIBE employees");
    echo implode(", ", $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>