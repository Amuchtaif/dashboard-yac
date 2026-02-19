<?php
$host = 'localhost'; $db_name = 'attendance_db'; $username = 'root'; $password = '';
try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->exec("UPDATE locations SET is_active = 1 WHERE is_active IS NULL OR is_active = 0");
    echo "Updated locations to be active";
} catch (PDOException $e) { echo $e->getMessage(); }
?>
