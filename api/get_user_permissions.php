<?php
// api/get_user_permissions.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/database.php';

// Support both GET and POST
$user_id = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = isset($input['user_id']) ? $input['user_id'] : '';
} else {
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';
}

if (empty($user_id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Parameter user_id is required."]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Query to get all permissions for a user via positions table
    $query = "SELECT 
                p.id as position_id,
                p.name as position_name,
                p.level as position_level,
                p.can_create_meeting,
                p.can_approve_permits
              FROM employees e 
              JOIN positions p ON e.position_id = p.id 
              WHERE e.id = :user_id 
              LIMIT 1";
              
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Build permissions object
        $permissions = [
            "can_create_meeting" => (bool)$row['can_create_meeting'],
            "can_approve_permits" => isset($row['can_approve_permits']) ? (bool)$row['can_approve_permits'] : false,
        ];
        
        echo json_encode([
            "success" => true,
            "data" => [
                "position_id" => (int)$row['position_id'],
                "position_name" => $row['position_name'],
                "position_level" => (int)$row['position_level'],
                "permissions" => $permissions
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "User not found or no position assigned."
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
}
?>
