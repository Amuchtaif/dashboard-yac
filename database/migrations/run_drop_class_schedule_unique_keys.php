<?php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = (new Database())->getConnection();
    
    echo "Running migration to drop legacy unique keys on class_schedules...\n";
    
    $stmt = $db->query("SHOW INDEX FROM class_schedules WHERE Key_name IN ('uq_class_schedule', 'uq_teacher_schedule')");
    if ($stmt && $stmt->rowCount() > 0) {
        $existing_keys = array_unique(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Key_name'));
        foreach ($existing_keys as $key_name) {
            $db->exec("ALTER TABLE class_schedules DROP INDEX `" . $key_name . "`");
            echo "Successfully dropped index: {$key_name}\n";
        }
    } else {
        echo "No legacy unique keys found on class_schedules. Database is up to date.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}
