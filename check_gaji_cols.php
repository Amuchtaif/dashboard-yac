<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query('DESCRIBE gaji');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Columns in 'gaji':\n";
foreach($res as $row) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
