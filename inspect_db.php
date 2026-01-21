<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

function dumpTable($conn, $table)
{
    echo "--- Structure for table: $table ---\n";
    try {
        $stmt = $conn->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . "\n\n";

        echo "Columns:\n";
        $stmt = $conn->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            print_r($col);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

dumpTable($conn, 'academic_years');
?>