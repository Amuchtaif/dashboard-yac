<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$overrides = $conn->query("SELECT * FROM ramadan_overrides")->fetchAll(PDO::FETCH_ASSOC);
foreach ($overrides as $ov) {
    echo "ID: {$ov['id']} | Times: {$ov['start_time']} - {$ov['end_time']} | Units: {$ov['unit_ids']}\n";
}
?>
