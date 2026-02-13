<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- INDEXES for tahfidz_teacher_attendance ---\n";
try {
    $stmt = $conn->query("SHOW INDEX FROM tahfidz_teacher_attendance");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($indexes as $row) {
        printf("Key: %-20s | Unique: %-3s | Column: %-20s\n", 
            $row['Key_name'], 
            ($row['Non_unique'] == 0 ? 'Yes' : 'No'), 
            $row['Column_name']
        );
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
