<?php
// database/migrations/run_user_feedbacks_migration.php
require_once __DIR__ . '/../../config/database.php';

echo "Running Migration: Create user_feedbacks table...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $sqlFile = __DIR__ . '/2026_11_25_create_user_feedbacks.sql';
    if (!file_exists($sqlFile)) {
        echo "Error: Migration SQL file not found at {$sqlFile}\n";
        exit(1);
    }

    $sqlContent = file_get_contents($sqlFile);
    $queries = array_filter(array_map('trim', explode(';', $sqlContent)));

    foreach ($queries as $query) {
        if (empty($query)) continue;
        echo "Executing: " . substr(preg_replace('/\s+/', ' ', $query), 0, 80) . "...\n";
        $conn->exec($query);
    }
    echo "Migration completed successfully!\n";

    echo "\n--- Inspecting employees and users tables ---\n";
    foreach (['employees', 'users', 'units'] as $tbl) {
        echo "\nTable: {$tbl}\n";
        try {
            $stmt = $conn->query("DESCRIBE {$tbl}");
            while ($row = $stmt->fetch()) {
                echo "  - {$row['Field']} ({$row['Type']})\n";
            }
        } catch (Exception $ex) {
            echo "  Table {$tbl} not found or error: " . $ex->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
