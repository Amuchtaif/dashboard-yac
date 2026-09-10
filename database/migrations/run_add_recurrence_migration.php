<?php
// database/migrations/run_add_recurrence_migration.php
require_once __DIR__ . '/../../config/database.php';

echo "Running Migration: Add recurrence and visibility columns to academic_calendar...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sqlFile = __DIR__ . '/2026_09_09_add_recurrence_and_visibility_to_academic_calendar.sql';
    if (!file_exists($sqlFile)) {
        echo "Error: Migration SQL file not found at {$sqlFile}\n";
        exit(1);
    }

    $sqlContent = file_get_contents($sqlFile);
    $queries = array_filter(array_map('trim', explode(';', $sqlContent)));

    foreach ($queries as $query) {
        // Remove SQL comments
        $cleanQuery = preg_replace('/--.*$/m', '', $query);
        $cleanQuery = trim($cleanQuery);
        if (empty($cleanQuery)) continue;

        echo "Executing: " . substr(str_replace(["\r", "\n"], ' ', $cleanQuery), 0, 60) . "...\n";
        $conn->exec($cleanQuery);
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
