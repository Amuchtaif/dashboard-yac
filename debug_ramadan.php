<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
echo "--- RAMADAN SETTINGS ---\n";
print_r($conn->query("SELECT * FROM ramadan_settings")->fetchAll(PDO::FETCH_ASSOC));
echo "--- RAMADAN OVERRIDES ---\n";
print_r($conn->query("SELECT * FROM ramadan_overrides")->fetchAll(PDO::FETCH_ASSOC));
echo "--- UNITS AFFECTED ---\n";
print_r($conn->query("SELECT id, name, is_ramadan_affected FROM units WHERE is_ramadan_affected = 1")->fetchAll(PDO::FETCH_ASSOC));
?>
