<?php
header('Content-Type: application/json');
require_once '../../config/app.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['student_id']) || empty($data['assessment_date'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$id = $data['id'] ?? null;
$student_id = $data['student_id'];
$assessment_date = $data['assessment_date'];
$assessment_type_id = $data['assessment_type_id'];
$teacher_id = $data['teacher_id'];
$tajweed_score = (int)$data['tajweed_score'];
$fluency_score = (int)$data['fluency_score'];
$makhraj_score = (int)$data['makhraj_score'];
$total_score = (int)$data['total_score'];
$comments = $data['comments'] ?? '';

// Get type name for compatibility with old category field
$type_name = "";
if ($assessment_type_id) {
    $stmt = $conn->prepare("SELECT name FROM tahfidz_assessment_types WHERE id = ?");
    $stmt->execute([$assessment_type_id]);
    $type_name = $stmt->fetchColumn();
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
