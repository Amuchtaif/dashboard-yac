<?php
require_once 'config/database.php';
$conn = (new Database())->getConnection();
$s = $conn->query("SELECT * FROM boarding_rooms");
print_r($s->fetchAll(PDO::FETCH_ASSOC));
?>
