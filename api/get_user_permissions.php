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

    // Query to get user and their position (if any)
    // Using LEFT JOIN so users without a position (e.g., Admin) are still found
    $query = "SELECT 
                e.id as employee_id,
                e.full_name,
                p.id as position_id,
                p.name as position_name,
                p.level as position_level,
                p.can_create_meeting,
                p.can_approve_permits,
                p.can_access_tahfidz,
                p.can_access_education
              FROM employees e 
              LEFT JOIN positions p ON e.position_id = p.id 
              WHERE e.id = :user_id 
              LIMIT 1";
              
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Build permissions object using hybrid permission logic
        include_once '../config/permission.php';
        
        // Check if user is Koordinator Tahfidz based on position name
        $posName = $row['position_name'] ?? '';
        $isKoordinator = (stripos($posName, 'Koordinator Tahfidz') !== false) ? 1 : 0;
        
        // Dynamic Access for Education Menu (Teacher Check)
        $stmtTeacher = $db->prepare("SELECT COUNT(*) FROM class_schedules WHERE employee_id = ? LIMIT 1");
        $stmtTeacher->execute([$user_id]);
        $isTeacher = (int)$stmtTeacher->fetchColumn() > 0;

        // Check if user is Wali Kelas
        $stmtWali = $db->prepare("SELECT COUNT(*) FROM grade_levels WHERE teacher_id = ? LIMIT 1");
        $stmtWali->execute([$user_id]);
        $isWali = (int)$stmtWali->fetchColumn() > 0;

        $permissions = [
            "can_create_meeting" => (int)hasPermission($user_id, 'create_meeting'),
            "can_approve_permits" => (int)hasPermission($user_id, 'approve_permits'),
            "can_access_tahfidz" => (int)hasPermission($user_id, 'access_tahfidz'),
            "can_access_education" => (hasPermission($user_id, 'access_education') || $isTeacher) ? 1 : 0,
            "can_manage_news" => (int)hasPermission($user_id, 'manage_news'),
            "can_create_assignment" => (int)hasPermission($user_id, 'manage_assignments'),
            "can_access_kabid" => (int)hasPermission($user_id, 'can_access_kabid'),
            "can_access_kesantrian" => (int)hasPermission($user_id, 'can_access_kesantrian'),
            "is_koordinator" => $isKoordinator,
            "is_wali_kelas" => $isWali,
        ];
        
        echo json_encode([
            "success" => true,
            "data" => [
                "position_id" => $row['position_id'] ? (int)$row['position_id'] : null,
                "position_name" => $row['position_name'] ?? 'No Position',
                "position_level" => $row['position_level'] ? (int)$row['position_level'] : 99,
                "permissions" => $permissions
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "User not found."
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
