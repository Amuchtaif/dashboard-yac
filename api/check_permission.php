<?php
// api/check_permission.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/database.php';

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "Parameter user_id is required."]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Query to check permission via positions table
    $query = "SELECT p.can_create_meeting 
              FROM employees e 
              JOIN positions p ON e.position_id = p.id 
              WHERE e.id = :user_id LIMIT 1";
              
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $can_create = (bool)$row['can_create_meeting'];
        
        echo json_encode([
            "success" => true,
            "can_create_meeting" => $can_create
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "User not found or no position assigned."
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
