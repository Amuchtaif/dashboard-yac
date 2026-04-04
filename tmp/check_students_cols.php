<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("DESCRIBE students");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
