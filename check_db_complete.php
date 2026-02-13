<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- COLS for tahfidz_teacher_attendance ---\n";
try {
    $stmt = $conn->query("DESCRIBE tahfidz_teacher_attendance");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Key'] . "\n";
    }
    
    echo "\n--- INDEXES for tahfidz_teacher_attendance ---\n";
    $stmt = $conn->query("SHOW INDEX FROM tahfidz_teacher_attendance");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Key: " . $row['Key_name'] . " | Unique: " . ($row['Non_unique'] == 0 ? 'Yes' : 'No') . " | Column: " . $row['Column_name'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
