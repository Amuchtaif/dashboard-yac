<?php
require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("DESCRIBE attendance");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "COLS: " . implode(", ", $cols) . "\n";

$stmt = $conn->query("SELECT * FROM attendance ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    foreach ($row as $k => $v) {
        echo "$k=$v\n";
    }
} else {
    echo "NO ROWS";
}
?>