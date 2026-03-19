<?php
require 'config/PayrollDatabase.php';
$db = new PayrollDatabase();
$conn = $db->getConnection();
$stmt = $conn->query("DESCRIBE gaji");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) {
    if (stripos($c['Field'], 'thr') !== false || stripos($c['Field'], 'bulan') !== false) {
        echo "FOUND: " . $c['Field'] . "\n";
    }
}
echo "TOTAL COLS: " . count($cols) . "\n";
