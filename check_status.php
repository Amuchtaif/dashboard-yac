<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$statuses = $conn->query("SELECT DISTINCT status FROM students")->fetchAll(PDO::FETCH_COLUMN);

echo "Distinct Statuses in DB:\n";
foreach ($statuses as $s) {
    echo "- '" . $s . "'\n";
}
?>