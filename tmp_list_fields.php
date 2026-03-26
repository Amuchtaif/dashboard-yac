<?php
require_once 'config/database.php';
$conn = (new Database())->getConnection();
$s = $conn->query("DESCRIBE students");
while($r = $s->fetch()) echo $r['Field'] . "\n";
?>
