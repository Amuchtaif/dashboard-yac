<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query('SELECT * FROM gaji ORDER BY id DESC LIMIT 1');
file_put_contents('test_data.txt', json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
