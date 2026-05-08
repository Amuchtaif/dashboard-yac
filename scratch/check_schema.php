<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query('SHOW CREATE TABLE class_schedules');
$res = $stmt->fetch();
echo $res['Create Table'];
?>
