<?php
require_once 'config/database.php';
$conn = (new Database())->getConnection();
$s = $conn->query("SHOW INDEX FROM meal_attendances");
print_r($s->fetchAll(PDO::FETCH_ASSOC));
?>
