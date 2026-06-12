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

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true) ?? [];

if (!$data || empty($data['student_id']) || empty($data['assessment_date'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$id = !empty($data['id']) ? $data['id'] : null;
$student_id = !empty($data['student_id']) ? $data['student_id'] : null;
$assessment_date = !empty($data['assessment_date']) ? $data['assessment_date'] : date('Y-m-d');
$assessment_type_id = !empty($data['assessment_type_id']) ? $data['assessment_type_id'] : null;
$teacher_id = !empty($data['teacher_id']) ? $data['teacher_id'] : ($_SESSION['user_id'] ?? null);
$tajweed_score = (int)($data['tajweed_score'] ?? 0);
$fluency_score = (int)($data['fluency_score'] ?? 0);
$makhraj_score = (int)($data['makhraj_score'] ?? 0);
$total_score = (int)($data['total_score'] ?? 0);
$comments = $data['comments'] ?? '';

// Get type name for compatibility with old category field
$type_name = "";
if ($assessment_type_id) {
    $stmt = $conn->prepare("SELECT name FROM tahfidz_assessment_types WHERE id = ?");
    $stmt->execute([$assessment_type_id]);
    $type_name = $stmt->fetchColumn() ?: "Lainnya";
} else {
    $type_name = !empty($data['category']) ? $data['category'] : "Bulanan";
}

try {
    if ($id) {
        $sql = "UPDATE tahfidz_assessments SET 
                student_id = ?, assessment_date = ?, category = ?, assessment_type_id = ?, 
                tajweed_score = ?, fluency_score = ?, makhraj_score = ?, total_score = ?, 
                comments = ?, teacher_id = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $student_id, $assessment_date, $type_name, $assessment_type_id,
            $tajweed_score, $fluency_score, $makhraj_score, $total_score,
            $comments, $teacher_id, $id
        ]);
        $message = "Penilaian berhasil diperbarui.";
    } else {
        $sql = "INSERT INTO tahfidz_assessments (
                    student_id, assessment_date, category, assessment_type_id, 
                    tajweed_score, fluency_score, makhraj_score, total_score, 
                    comments, teacher_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $student_id, $assessment_date, $type_name, $assessment_type_id,
            $tajweed_score, $fluency_score, $makhraj_score, $total_score,
            $comments, $teacher_id
        ]);
        $message = "Penilaian baru berhasil disimpan.";
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
