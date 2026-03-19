<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query('DESCRIBE gaji');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cols = [];
foreach($res as $row) {
    $cols[] = $row['Field'];
}
file_put_contents('gaji_schema_utf8.txt', implode("\n", $cols));
