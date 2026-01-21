<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // Check if name column exists
    $stmt = $conn->query("SHOW COLUMNS FROM positions LIKE 'name'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE positions ADD COLUMN name VARCHAR(255) NOT NULL AFTER id");
        echo "Added 'name' column to positions table.\n";
    } else {
        echo "'name' column already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>