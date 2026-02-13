<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Check if employee_assignments table exists and its structure
try {
    $stmt = $conn->query("DESCRIBE employee_assignments");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "EXISTS\n";
    print_r($structure);
} catch (Exception $e) {
    echo "NOT_EXISTS: " . $e->getMessage() . "\n";
}

// Also check positions for 'Koordinator Tahfidz' ID
$stmt = $conn->query("SELECT id, name FROM positions WHERE name LIKE '%Koordinator Tahfidz%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
