<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak valid"]);
    exit;
}

$assessment_id = !empty($data['assessment_id']) ? (int)$data['assessment_id'] : (!empty($data['id']) ? (int)$data['id'] : null);

if (!$assessment_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID Penilaian diperlukan untuk update"]);
    exit;
}

$required_fields = ['class_id', 'subject_id', 'assessment_type_id', 'date', 'scores'];
$missing = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || (is_string($data[$field]) && empty($data[$field]))) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode([
        "success" => false, 
        "message" => "Data tidak lengkap", 
        "missing_fields" => $missing
    ]);
    exit;
}

try {
    $db->beginTransaction();

    // 1. Update Header
    $query = "UPDATE student_assessments SET 
                grade_level_id = :grade_level_id, 
                subject_id = :subject_id, 
                assessment_type_id = :assessment_type_id, 
                assessment_date = :assessment_date, 
                teacher_id = :teacher_id
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $assessment_id);
    $stmt->bindParam(':grade_level_id', $data['class_id']);
    $stmt->bindParam(':subject_id', $data['subject_id']);
    $stmt->bindParam(':assessment_type_id', $data['assessment_type_id']);
    $stmt->bindParam(':assessment_date', $data['date']);
    
    $teacher_id = !empty($data['teacher_id']) ? $data['teacher_id'] : null;
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->execute();

    // 2. Clear old scores
    $del_query = "DELETE FROM student_assessment_details WHERE assessment_id = :aid";
    $del_stmt = $db->prepare($del_query);
    $del_stmt->bindParam(':aid', $assessment_id);
    $del_stmt->execute();

    // 3. Insert new scores
    $query_detail = "INSERT INTO student_assessment_details (assessment_id, student_id, score) 
                     VALUES (:assessment_id, :student_id, :score)";
    $stmt_detail = $db->prepare($query_detail);

    foreach ($data['scores'] as $row) {
        $student_id = $row['student_id'] ?? null;
        $score = $row['score'] ?? 0;
        
        if ($student_id !== null) {
            $stmt_detail->bindParam(':assessment_id', $assessment_id);
            $stmt_detail->bindParam(':student_id', $student_id);
            $stmt_detail->bindParam(':score', $score);
            $stmt_detail->execute();
        }
    }

    $db->commit();
    echo json_encode([
        "success" => true, 
        "message" => "Data penilaian berhasil diperbarui", 
        "assessment_id" => $assessment_id
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Terjadi kesalahan: " . $e->getMessage()]);
}
?>
