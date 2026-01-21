<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->query("SHOW TABLES LIKE 'positions'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'positions' exists.\n";
        $columns = $conn->query("DESCRIBE positions")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo $col['Field'] . " - " . $col['Type'] . "\n";
        }
    } else {
        echo "Table 'positions' does not exist.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>