<?php
// api/tahfidz/submit_assessment.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/db_mysqli.php';

$input = json_decode(file_get_contents("php://input"), true);

$student_id = isset($input['student_id']) ? $input['student_id'] : null;
$assessment_date = isset($input['assessment_date']) ? $input['assessment_date'] : date('Y-m-d');
$category = isset($input['category']) ? $input['category'] : 'Bulanan';
$tajweed_score = isset($input['tajweed_score']) ? $input['tajweed_score'] : 0;
$fluency_score = isset($input['fluency_score']) ? $input['fluency_score'] : 0;
$makhraj_score = isset($input['makhraj_score']) ? $input['makhraj_score'] : 0;
// Calculate total if not provided, or take provided
$total_score = isset($input['total_score']) ? $input['total_score'] : ($tajweed_score + $fluency_score + $makhraj_score) / 3; 

$comments = isset($input['comments']) ? $input['comments'] : '';
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;

// Check Permission
include_once '../../config/permission.php';
if ($teacher_id && !hasPermission($teacher_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki akses Tahfidz."]);
    exit;
}

if (!$student_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Student ID is required"]);
    exit;
}

try {
    $assessment_type_id = isset($input['assessment_type_id']) ? $input['assessment_type_id'] : null;
    
    // Get type name for category fallback
    if ($assessment_type_id) {
        $res = $mysqli->query("SELECT name FROM tahfidz_assessment_types WHERE id = " . (int)$assessment_type_id);
        if ($res && $row = $res->fetch_assoc()) {
            $category = $row['name'];
        }
    }

    $stmt = $mysqli->prepare("INSERT INTO tahfidz_assessments 
        (student_id, assessment_date, category, assessment_type_id, tajweed_score, fluency_score, makhraj_score, total_score, comments, teacher_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("issiiiiisi", 
        $student_id, 
        $assessment_date, 
        $category,
        $assessment_type_id, 
        $tajweed_score, 
        $fluency_score, 
        $makhraj_score, 
        $total_score, 
        $comments, 
        $teacher_id
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Assessment saved successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to save assessment: " . $stmt->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
