<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/app.php';
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
    // 1. Find group_id for this teacher
    $stmt = $conn->prepare("SELECT id, group_name FROM halaqah_groups WHERE teacher_id = ? LIMIT 1");
    $stmt->execute([$teacher_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Musyrif belum memiliki grup halaqah"]);
        exit;
    }

    $group_id = $group['id'];
    $group_name = $group['group_name'];

    // Fetch student name before deletion
    $s_stmt = $conn->prepare("SELECT nama_siswa FROM students WHERE id = ? LIMIT 1");
    $s_stmt->execute([$student_id]);
    $s_name = $s_stmt->fetchColumn() ?: "ID $student_id";

    // 2. Remove student from halaqah
    $stmt_del = $conn->prepare("DELETE FROM halaqah_members WHERE group_id = ? AND student_id = ?");
    $stmt_del->execute([$group_id, $student_id]);

    Logger::activity(
        'Tahfidz',
        'REMOVE_HALAQAH_MEMBER',
        "Menghapus santri '$s_name' dari halaqah binaan '$group_name'",
        [
            'table' => 'halaqah_members',
            'old_data' => ['group_id' => $group_id, 'group_name' => $group_name, 'student_id' => $student_id, 'nama_siswa' => $s_name]
        ]
    );

    echo json_encode([
        "success" => true,
        "message" => "Santri berhasil dihapus dari halaqah binaan"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
