<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- INDEXES for tahfidz_attendance ---\n";
try {
    $stmt = $conn->query("SHOW INDEX FROM tahfidz_attendance");
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit;
}
$indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($indexes as $row) {
    printf("Key: %-20s | Unique: %-3s | Column: %-20s\n", 
        $row['Key_name'], 
        ($row['Non_unique'] == 0 ? 'Yes' : 'No'), 
        $row['Column_name']
    );
}

// Add unique index if missing for ON DUPLICATE KEY to work correctly
$found = false;
foreach ($indexes as $row) {
    if ($row['Non_unique'] == 0 && $row['Key_name'] !== 'PRIMARY') {
        $found = true;
    }
}

if (!$found) {
    echo "Adding unique index for student attendance (student_id, date, session)\n";
    try {
        $conn->exec("ALTER TABLE tahfidz_attendance ADD UNIQUE KEY `unique_attendance` (student_id, date, session)");
    } catch (Exception $e) {
        echo "Error adding unique index: " . $e->getMessage() . "\n";
    }
}
