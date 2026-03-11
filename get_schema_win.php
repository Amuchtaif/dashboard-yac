<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SHOW CREATE TABLE class_schedules");
$row = $stmt->fetch(PDO::FETCH_NUM);
file_put_contents('table_schema.txt', $row[1]);
echo "Schema written to table_schema.txt";
