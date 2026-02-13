<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- TABLES ---\n";
$stmt = $conn->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . "\n";
}

echo "\n--- EMPLOYEES COLUMNS ---\n";
$stmt = $conn->query("DESCRIBE employees");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n--- STUDENTS COLUMNS ---\n";
try {
    $stmt = $conn->query("DESCRIBE students");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "No students table or error: " . $e->getMessage() . "\n";
}
