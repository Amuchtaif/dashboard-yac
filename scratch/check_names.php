<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, full_name FROM employees LIMIT 20");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ": " . $row['full_name'] . "\n";
}
