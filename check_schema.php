<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    echo "Columns in academic_years:\n";
    $query = $conn->query("DESCRIBE academic_years");
    $columns = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>