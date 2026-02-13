<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- INDEXES for tahfidz_teacher_attendance ---\n";
try {
    $stmt = $conn->query("SHOW INDEX FROM tahfidz_teacher_attendance");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Table: " . $row['Table'] . " | Non_unique: " . $row['Non_unique'] . " | Key_name: " . $row['Key_name'] . " | Column_name: " . $row['Column_name'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
