<?php
require_once 'config/database.php';
$conn = (new Database())->getConnection();
$s = $conn->query("SELECT tingkat, COUNT(*) as count FROM students GROUP BY tingkat");
print_r($s->fetchAll(PDO::FETCH_ASSOC));
?>
