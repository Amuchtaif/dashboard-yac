<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

function printTable($conn, $table) {
    echo "\n--- $table COLUMNS ---\n";
    try {
        $stmt = $conn->query("DESCRIBE `$table`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['Field'] . " | " . $row['Type'] . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

printTable($conn, 'employees');
printTable($conn, 'students');
printTable($conn, 'tahfidz_memorization');
