<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$tables = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $tables);
