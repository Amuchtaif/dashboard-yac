<?php
$host = 'localhost';
$db_name = 'attendance_db';
$username = 'root';
$password = '';
try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $stmt = $conn->query("SELECT COUNT(*) as total FROM locations");
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
} catch (PDOException $e) { echo $e->getMessage(); }
?>
