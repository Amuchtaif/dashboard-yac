<?php
$host = 'localhost';
$db_name = 'attendance_db';
$username = 'root';
$password = '';
try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $stmt = $conn->query("DESCRIBE locations");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) { echo $e->getMessage(); }
?>
