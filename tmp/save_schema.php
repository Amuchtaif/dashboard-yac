<?php
require_once 'config/database.php';
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->query('SHOW CREATE TABLE boarding_attendances');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    file_put_contents('tmp/table_schema.txt', $row['Create Table']);
} catch (Exception $e) {
    echo $e->getMessage();
}
