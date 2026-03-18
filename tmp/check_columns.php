<?php
require_once 'config/database.php';
try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->query('DESCRIBE boarding_attendances');
    foreach($stmt->fetchAll() as $row) {
        echo $row['Field'] . "\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
