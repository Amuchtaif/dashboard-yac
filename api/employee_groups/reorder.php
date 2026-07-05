<?php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'PUT';

if ($method === 'PUT' || $method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!$input || !isset($input['ids']) || !is_array($input['ids'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "success" => false, "message" => "Invalid input: 'ids' must be an array of integers."]);
        exit;
    }
    
    $ids = $input['ids'];
    
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "success" => false, "message" => "Invalid input: 'ids' array cannot be empty."]);
        exit;
    }
    
    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("UPDATE employee_groups SET position = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $mysqli->error);
        }
        
        $position = 1;
        foreach ($ids as $id) {
            $group_id = (int)$id;
            $stmt->bind_param("ii", $position, $group_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update position for ID {$group_id}: " . $stmt->error);
            }
            $position++;
        }
        $stmt->close();
        
        $mysqli->commit();
        echo json_encode([
            "status" => "success",
            "success" => true,
            "message" => "Employee groups reordered successfully."
        ]);
        
    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "success" => false,
            "message" => "Failed to reorder employee groups: " . $e->getMessage()
        ]);
    }
    exit;
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}
?>
