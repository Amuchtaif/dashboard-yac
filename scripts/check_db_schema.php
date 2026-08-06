<?php
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- DIVISIONS ---\n";
print_r($conn->query("SELECT id, name FROM divisions")->fetchAll(PDO::FETCH_ASSOC));

echo "--- UNITS ---\n";
print_r($conn->query("SELECT id, division_id, name FROM units")->fetchAll(PDO::FETCH_ASSOC));
?>
