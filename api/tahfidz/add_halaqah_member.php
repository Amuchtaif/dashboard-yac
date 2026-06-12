<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->teacher_id) || !isset($data->student_id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Teacher ID and Student ID are required"]);
    exit;
}

$teacher_id = $data->teacher_id;
$student_id = $data->student_id;

try {
    $conn->beginTransaction();

    // 1. Get or create halaqah group for this teacher
    $stmt = $conn->prepare("SELECT id FROM halaqah_groups WHERE teacher_id = ? LIMIT 1");
    $stmt->execute([$teacher_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        // Get teacher name
        $stmt_name = $conn->prepare("SELECT full_name FROM employees WHERE id = ?");
        $stmt_name->execute([$teacher_id]);
        $teacher = $stmt_name->fetch(PDO::FETCH_ASSOC);
        $group_name = "Halaqah " . ($teacher['full_name'] ?? "Musyrif");

        $stmt_create = $conn->prepare("INSERT INTO halaqah_groups (group_name, teacher_id) VALUES (?, ?)");
        $stmt_create->execute([$group_name, $teacher_id]);
        $group_id = $conn->lastInsertId();
    } else {
        $group_id = $group['id'];
    }

    // 2. Add student to group
    $stmt_add = $conn->prepare("INSERT IGNORE INTO halaqah_members (group_id, student_id) VALUES (?, ?)");
    $stmt_add->execute([$group_id, $student_id]);

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Santri berhasil ditambahkan ke halaqah binaan",
        "group_id" => $group_id
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
