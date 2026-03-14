<?php require 'config/database.php'; $db = new Database(); $conn = $db->getConnection(); $stmt = $conn->query('SELECT * FROM ramadan_settings'); print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); ?>
