<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $table = $row[0];
    if (strpos($table, 'boarding') !== false || strpos($table, 'student') !== false) {
        echo "Table: $table\n";
        $cols = $conn->query("SHOW COLUMNS FROM $table");
        while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
            echo "  " . $col['Field'] . " | ";
        }
        echo "\n\n";
    }
}
?>
