<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
file_put_contents('tables_list.txt', implode("\n", $tables));
echo "Tables listed in tables_list.txt";
