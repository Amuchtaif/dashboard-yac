<?php
$host = 'localhost'; $db_name = 'attendance_db'; $username = 'root'; $password = '';
try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->exec("ALTER TABLE locations ADD COLUMN address VARCHAR(255) NULL AFTER name");
    echo "Added address column to locations";
} catch (PDOException $e) { echo $e->getMessage(); }
?>
