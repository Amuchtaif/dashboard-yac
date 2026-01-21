<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SELECT * FROM academic_years LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "Keys found in row: " . implode(", ", array_keys($row)) . "\n";
    print_r($row);
} else {
    echo "No rows found in academic_years.\n";
    // Let's insert a dummy row to test
    try {
        $conn->exec("INSERT INTO academic_years (name, status) VALUES ('Test Year', 'Inactive')");
        echo "Inserted test row.\n";
        $stmt = $conn->query("SELECT * FROM academic_years LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Keys found after insert: " . implode(", ", array_keys($row)) . "\n";
    } catch (Exception $e) {
        echo "Insert failed: " . $e->getMessage() . "\n";
    }
}

$cols = $conn->query("SHOW COLUMNS FROM academic_years")->fetchAll(PDO::FETCH_ASSOC);
echo "\nDetailed Columns:\n";
foreach ($cols as $col) {
    echo "Field: {$col['Field']}, Type: {$col['Type']}\n";
}
?>