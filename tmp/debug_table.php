<?php
require_once 'config/database.php';
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->query('SHOW CREATE TABLE boarding_attendances');
    print_r($stmt->fetch());
} catch (Exception $e) {
    echo $e->getMessage();
}
