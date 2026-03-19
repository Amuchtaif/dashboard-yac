<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query('SELECT gaji_bulan, tanggal, created_at FROM gaji ORDER BY id DESC LIMIT 20');
file_put_contents('data_samples.txt', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
