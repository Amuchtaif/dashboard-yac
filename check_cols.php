<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
print_r($conn->query("SHOW COLUMNS FROM ramadan_settings")->fetchAll(PDO::FETCH_ASSOC));
?>
