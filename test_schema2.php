<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query('SHOW TABLES');
file_put_contents('test_schema.txt', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
