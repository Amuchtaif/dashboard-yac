<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query('DESCRIBE gaji');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $row) {
    echo $row['Field'] . "\n";
}
