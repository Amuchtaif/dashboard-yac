<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query('DESCRIBE gaji');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Columns containing 'thr' or 'THR':\n";
foreach($res as $row) {
    if (stripos($row['Field'], 'thr') !== false) {
        echo "- " . $row['Field'] . "\n";
    }
}
echo "ALL COLUMNS:\n";
foreach($res as $row) {
    echo $row['Field'] . ", ";
}
echo "\n";
