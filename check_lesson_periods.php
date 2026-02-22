<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW TABLES LIKE 'lesson_periods'");
if ($stmt->rowCount() > 0) {
    $stmt = $conn->query("DESCRIBE lesson_periods");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " | " . $row['Type'] . "\n";
    }
    echo "Sample Data:\n";
    $stmt = $conn->query("SELECT * FROM lesson_periods LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    echo "Table lesson_periods does NOT exist.";
}
