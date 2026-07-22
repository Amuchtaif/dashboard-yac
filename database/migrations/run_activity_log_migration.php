<?php
// database/migrations/run_activity_log_migration.php
require_once __DIR__ . '/../../config/database.php';

echo "Running Migration: Create activity_logs table...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if table already exists
    $check = $conn->query("SHOW TABLES LIKE 'activity_logs'")->fetch();
    if ($check) {
        echo "Migration skipped: 'activity_logs' table already exists.\n";
        exit(0);
    }

    // Read SQL file
    $sqlFile = __DIR__ . '/2026_07_19_create_activity_logs.sql';
    if (!file_exists($sqlFile)) {
        echo "Error: Migration SQL file not found at {$sqlFile}\n";
        exit(1);
    }

    $sqlContent = file_get_contents($sqlFile);
    // Split SQL by semicolon, filtering empty lines
    $queries = array_filter(array_map('trim', explode(';', $sqlContent)));

    foreach ($queries as $query) {
        if (empty($query)) continue;
        echo "Executing: " . substr($query, 0, 50) . "...\n";
        $conn->exec($query);
    }
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
