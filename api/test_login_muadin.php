<?php
// Test login API for Muadin
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Query yang sama dengan login.php
    $email = 'abuukasyah77@gmail.com'; // Email Muadin
    
    $query = "SELECT 
                e.id, 
                e.full_name, 
                e.email, 
                e.phone_number,
                e.password, 
                u.name AS unit_name, 
                d.name AS division_name,
                p.level AS position_level,
                p.name AS position_name
              FROM employees e 
              LEFT JOIN positions p ON e.position_id = p.id
              LEFT JOIN units u ON e.unit_id = u.id 
              LEFT JOIN divisions d ON e.division_id = d.id 
              WHERE e.email = :email";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        unset($user['password']);
        echo json_encode([
            "success" => true,
            "message" => "Data ditemukan",
            "data" => $user
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "User tidak ditemukan"
        ], JSON_PRETTY_PRINT);
    }
    
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
