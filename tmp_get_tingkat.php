<?php
require_once 'config/database.php';
$conn = (new Database())->getConnection();
$s = $conn->query("SELECT DISTINCT tingkat FROM students");
while($r = $s->fetch()) echo $r['tingkat'] . "\n";
?>
