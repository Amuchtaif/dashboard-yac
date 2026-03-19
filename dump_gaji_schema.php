<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query('DESCRIBE gaji');
file_put_contents('gaji_schema.txt', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
