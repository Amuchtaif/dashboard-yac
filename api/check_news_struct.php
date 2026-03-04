<?php
include_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("DESCRIBE news");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
