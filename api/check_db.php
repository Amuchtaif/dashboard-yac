<?php
// Database configuration
$host = 'localhost';
$db_name = 'attendance_db';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents('employees_structure.json', json_encode($columns));
    echo "Done writing employees_structure.json";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
