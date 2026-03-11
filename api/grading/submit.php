<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

$required_fields = ['class_id', 'subject_id', 'assessment_type_id', 'date', 'scores'];
$missing = [];

foreach ($required_fields as $field) {
    if (empty($data->$field)) {
        $missing[] = $field;
    }
}

if (empty($missing) && is_array($data->scores)) {
    try {
        $db->beginTransaction();

        // 1. Insert into student_assessments
        $query = "INSERT INTO student_assessments (grade_level_id, subject_id, assessment_type_id, assessment_date, teacher_id) 
                  VALUES (:grade_level_id, :subject_id, :assessment_type_id, :assessment_date, :teacher_id)";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':grade_level_id', $data->class_id);
        $stmt->bindParam(':subject_id', $data->subject_id);
        $stmt->bindParam(':assessment_type_id', $data->assessment_type_id);
        $stmt->bindParam(':assessment_date', $data->date);
        
        $teacher_id = !empty($data->teacher_id) ? $data->teacher_id : null;
        $stmt->bindParam(':teacher_id', $teacher_id);

        if ($stmt->execute()) {
            $assessment_id = $db->lastInsertId();

            $query_detail = "INSERT INTO student_assessment_details (assessment_id, student_id, score) 
                             VALUES (:assessment_id, :student_id, :score)";
            $stmt_detail = $db->prepare($query_detail);

            foreach ($data->scores as $row) {
                // Handle both object and array format for scores
                $student_id = isset($row->student_id) ? $row->student_id : (isset($row['student_id']) ? $row['student_id'] : null);
                $score = isset($row->score) ? $row->score : (isset($row['score']) ? $row['score'] : 0);
                
                if ($student_id !== null) {
                    $stmt_detail->bindParam(':assessment_id', $assessment_id);
                    $stmt_detail->bindParam(':student_id', $student_id);
                    $stmt_detail->bindParam(':score', $score);
                    $stmt_detail->execute();
                }
            }

            $db->commit();

            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Data penilaian berhasil disimpan", "assessment_id" => $assessment_id]);
        } else {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Gagal menyimpan header penilaian"]);
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Terjadi kesalahan: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false, 
        "message" => "Data tidak lengkap atau format scores salah", 
        "missing_fields" => $missing,
        "received_data" => $data // Membantu debug field apa yang masuk
    ]);
}
?>
