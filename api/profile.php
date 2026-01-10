<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) && !isset($data->email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID or Email required"]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $query = "
        SELECT 
            e.id, 
            e.full_name, 
            e.email, 
            d.name as department_name, 
            u.name as unit_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN units u ON e.unit_id = u.id
        WHERE ";

    if (isset($data->user_id)) {
        $query .= "e.id = :id";
        $param = $data->user_id;
        $paramType = PDO::PARAM_INT;
    } else {
        $query .= "e.email = :email";
        $param = $data->email;
        $paramType = PDO::PARAM_STR;
    }

    $stmt = $conn->prepare($query);

    if (isset($data->user_id)) {
        $stmt->bindParam(':id', $data->user_id, PDO::PARAM_INT);
    } else {
        $stmt->bindParam(':email', $data->email, PDO::PARAM_STR);
    }

    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            "status" => "success",
            "data" => $user
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "User not found"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "System error: " . $e->getMessage()]);
}
