<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // 1. Drop unique constraint if exists
    // We don't know the exact name, so we'll look for it
    $stmt = $conn->query("SHOW INDEX FROM tahfidz_teacher_attendance");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($indexes as $index) {
        if ($index['Key_name'] !== 'PRIMARY' && $index['Non_unique'] == 0) {
            $key_name = $index['Key_name'];
            echo "Dropping unique index: $key_name\n";
            $conn->exec("ALTER TABLE tahfidz_teacher_attendance DROP INDEX `$key_name` ");
        }
    }

    echo "Table schema check completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
