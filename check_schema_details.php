<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- POSITIONS TABLE ---\n";
$stmt = $conn->query("DESCRIBE positions");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . " | " . $col['Type'] . "\n";
}

echo "\n--- EMPLOYEES TABLE ---\n";
$stmt = $conn->query("DESCRIBE employees");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    if (strpos($col['Field'], 'position') !== false) {
        echo $col['Field'] . " | " . $col['Type'] . "\n";
    }
}
?>