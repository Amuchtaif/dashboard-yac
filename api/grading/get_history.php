<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

try {
    $where_clauses = [];
    $params = [];

    if ($teacher_id > 0) {
        $where_clauses[] = "sa.teacher_id = :teacher_id";
        $params[':teacher_id'] = $teacher_id;
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $query = "
        SELECT 
            sa.id,
            sa.assessment_date,
            sa.created_at,
            s.name as subject_name, 
            gl.name as class_name, 
            at.name as assessment_type_name,
            (SELECT AVG(score) FROM student_assessment_details WHERE assessment_id = sa.id) as avg_score,
            (SELECT COUNT(*) FROM student_assessment_details WHERE assessment_id = sa.id) as student_count
        FROM student_assessments sa
        JOIN subjects s ON sa.subject_id = s.id
        JOIN grade_levels gl ON sa.grade_level_id = gl.id
        JOIN assessment_types at ON sa.assessment_type_id = at.id
        $where_sql
        ORDER BY sa.assessment_date DESC, sa.created_at DESC
        LIMIT :limit
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $results
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
