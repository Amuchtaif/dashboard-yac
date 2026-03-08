<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Cache-Control: public, max-age=30");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$rpp_id = $_GET['rpp_id'] ?? null;

if (!$rpp_id) {
    echo json_encode(["success" => false, "message" => "RPP ID is required"]);
    exit;
}

try {
    $query = "
        SELECT 
            r.*, 
            s.name as subject_name, 
            gl.name as grade_name, 
            ay.name as academic_year_name,
            e.full_name as teacher_name
        FROM rpp r
        JOIN subjects s ON r.subject_id = s.id
        JOIN grade_levels gl ON r.grade_level_id = gl.id
        JOIN academic_years ay ON r.academic_year_id = ay.id
        JOIN employees e ON r.employee_id = e.id
        WHERE r.id = ?
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$rpp_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $data = [
            "id" => (int)$row['id'],
            "employee_id" => (int)$row['employee_id'],
            "teacher_name" => $row['teacher_name'],
            "academic_year_id" => (int)$row['academic_year_id'],
            "academic_year" => $row['academic_year_name'],
            "semester" => $row['semester'],
            "grade_level_id" => (int)$row['grade_level_id'],
            "grade_name" => $row['grade_name'],
            "subject_id" => (int)$row['subject_id'],
            "subject_name" => $row['subject_name'],
            "title" => $row['title'],
            "content_sk" => $row['content_sk'],
            "content_kd" => $row['content_kd'],
            "content_indicator" => $row['content_indicator'],
            "content_steps" => $row['content_steps'],
            "content_summary" => $row['content_summary'],
            "is_draft" => (bool)$row['is_draft'],
            "created_at" => $row['created_at'],
            "updated_at" => $row['updated_at']
        ];

        echo json_encode([
            "success" => true,
            "data" => $data
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "RPP not found"]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
