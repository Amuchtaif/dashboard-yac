<?php
// database/migrations/run_position_migration.php
require_once __DIR__ . '/../../config/db_mysqli.php';

echo "Running Migration: Add Position Column to employee_groups...\n";

// Check if position column already exists
$check = $mysqli->query("SHOW COLUMNS FROM `employee_groups` LIKE 'position'");
if ($check && $check->num_rows > 0) {
    echo "Migration skipped: 'position' column already exists in 'employee_groups' table.\n";
    exit(0);
}

// Read SQL file
$sqlFile = __DIR__ . '/2026_07_05_add_position_to_employee_groups.sql';
if (!file_exists($sqlFile)) {
    echo "Error: Migration SQL file not found at {$sqlFile}\n";
    exit(1);
}

$sqlContent = file_get_contents($sqlFile);
// Split SQL by semicolon, filtering empty lines
$queries = array_filter(array_map('trim', explode(';', $sqlContent)));

$mysqli->begin_transaction();
try {
    foreach ($queries as $query) {
        if (empty($query)) continue;
        echo "Executing: {$query}...\n";
        if (!$mysqli->query($query)) {
            throw new Exception("Query failed: " . $mysqli->error);
        }
    }
    $mysqli->commit();
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    $mysqli->rollback();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
