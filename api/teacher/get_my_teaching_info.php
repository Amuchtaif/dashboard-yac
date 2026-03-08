<?php
header('Content-Type: application/json');
require '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$employee_id = $_GET['employee_id'] ?? $_GET['user_id'] ?? null;

if (!$employee_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing employee_id/user_id"]);
    exit;
}

try {
    // Get ALL teaching schedules for this employee to build the mapping
    $sql = "
        SELECT 
            gl.category as level_name,
            s.name as subject_name,
            gl.name as class_name
        FROM class_schedules cs
        JOIN grade_levels gl ON cs.grade_level_id = gl.id
        JOIN subjects s ON cs.subject_id = s.id
        WHERE cs.employee_id = :employee_id
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':employee_id' => $employee_id]);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $raw_data
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
