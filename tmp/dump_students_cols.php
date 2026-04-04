<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("DESCRIBE students");
file_put_contents('tmp/students_cols.json', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
?>
