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

if (!$student_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Student ID is required"]);
    exit;
}

try {
    $stmt = $mysqli->prepare("INSERT INTO tahfidz_assessments 
        (student_id, assessment_date, category, tajweed_score, fluency_score, makhraj_score, total_score, comments, teacher_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("issiiiisi", 
        $student_id, 
        $assessment_date, 
        $category, 
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
