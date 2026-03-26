<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$s = $conn->query("DESCRIBE students");
print_r($s->fetchAll(PDO::FETCH_ASSOC));
?>
