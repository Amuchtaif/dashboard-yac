<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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
            gl.category as level_name,
            eu.name as unit_name,
            ay.name as academic_year_name,
            e.full_name as teacher_name
        FROM rpp r
        LEFT JOIN subjects s ON r.subject_id = s.id
        LEFT JOIN grade_levels gl ON r.grade_level_id = gl.id
        LEFT JOIN education_units eu ON r.education_unit_id = eu.id
        LEFT JOIN academic_years ay ON r.academic_year_id = ay.id
        LEFT JOIN employees e ON r.employee_id = e.id
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
            "level_name" => $row['level_name'],
            "unit_name" => $row['unit_name'],
            "subject_id" => (int)$row['subject_id'],
            "subject_name" => $row['subject_name'],
            "title" => $row['title'],
            "content_cp" => $row['content_cp'],
            "content_atp" => $row['content_atp'],
            "content_pertanyaan_pemantik" => $row['content_pertanyaan_pemantik'],
            "content_steps" => $row['content_steps'],
            "content_summary" => $row['content_summary'],
            "session_no" => $row['session_no'],
            "allocation" => $row['allocation'],
            "learning_goal" => $row['learning_goal'],
            "teaching_material" => $row['teaching_material'],
            "teaching_profil_pancasila" => $row['teaching_profil_pancasila'],
            "assessment" => $row['assessment'],
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
