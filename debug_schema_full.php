<?php
require_once 'config/database.php';
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $col) {
        echo $col . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>