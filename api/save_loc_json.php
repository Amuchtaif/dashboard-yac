<?php
$host = 'localhost';
$db_name = 'attendance_db';
$username = 'root';
$password = '';
try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $stmt = $conn->query("DESCRIBE locations");
    file_put_contents('loc_schema.json', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
} catch (PDOException $e) { echo $e->getMessage(); }
?>
