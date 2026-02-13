<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- STUDENTS COLUMNS ---\n";
try {
    $stmt = $conn->query("DESCRIBE students");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " | " . $row['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
