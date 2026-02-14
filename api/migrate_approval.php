<?php
// api/migrate_approval.php
mysqli_report(MYSQLI_REPORT_OFF);

// Correct path using __DIR__
require_once __DIR__ . '/../config/db_mysqli.php';

if ($mysqli->connect_errno) {
    die("Connection failed: " . $mysqli->connect_error);
}

function addColumn($mysqli, $table, $colName, $definition) {
    // Check if column exists
    $result = $mysqli->query("SHOW COLUMNS FROM $table LIKE '$colName'");
    if ($result && $result->num_rows > 0) {
        echo "Column $colName already exists in $table.\n";
    } else {
        $sql = "ALTER TABLE $table ADD COLUMN $colName $definition";
        if ($mysqli->query($sql)) {
            echo "Added column $colName to $table.\n";
        } else {
            echo "Error adding $colName: " . $mysqli->error . "\n";
        }
    }
}

$table = 'tahfidz_teacher_attendance';
$checkTable = $mysqli->query("SHOW TABLES LIKE '$table'");
if (!$checkTable || $checkTable->num_rows == 0) {
    die("Table $table does not exist. Migration aborted.\n");
}

echo "Migrating $table...\n";
addColumn($mysqli, $table, 'status_approval', "ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
addColumn($mysqli, $table, 'approved_by', "INT NULL");
addColumn($mysqli, $table, 'approval_time', "DATETIME NULL");
addColumn($mysqli, $table, 'rejection_reason', "TEXT NULL");

echo "Migration Complete.\n";
?>
