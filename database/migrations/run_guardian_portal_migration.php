<?php
// database/migrations/run_guardian_portal_migration.php
require_once __DIR__ . '/../../config/database.php';

echo "Running Migration: Create Guardian (Wali Murid) Portal Tables...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $sqlFile = __DIR__ . '/2026_11_02_create_guardian_portal_tables.sql';
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
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

