<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Cache-Control: public, max-age=30");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$employee_id = $_GET['employee_id'] ?? null;
$is_draft = isset($_GET['is_draft']) ? (int)$_GET['is_draft'] : 0;
$search = $_GET['search'] ?? '';

if (!$employee_id) {
    echo json_encode(["success" => false, "message" => "Employee ID is required"]);
    exit;
}

try {
    $where = "r.employee_id = :eid AND r.is_draft = :draft";
    $params = [
        ':eid' => $employee_id,
        ':draft' => $is_draft
    ];

    if ($search) {
        $where .= " AND (r.title LIKE :search OR s.name LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $query = "
        SELECT 
            r.id, 
            r.title, 
            r.semester, 
            r.created_at, 
            r.is_draft, 
            s.name as subject_name, 
            gl.name as grade_name, 
            ay.name as academic_year_name
        FROM rpp r
        JOIN subjects s ON r.subject_id = s.id
        JOIN grade_levels gl ON r.grade_level_id = gl.id
        JOIN academic_years ay ON r.academic_year_id = ay.id
        WHERE $where
        ORDER BY r.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedData = [];
    foreach ($results as $row) {
        $formattedData[] = [
            "id" => (int)$row['id'],
            "title" => $row['title'],
            "subject_name" => $row['subject_name'],
            "grade_name" => $row['grade_name'],
            "semester" => $row['semester'],
            "academic_year" => $row['academic_year_name'],
            "is_draft" => (bool)$row['is_draft'],
            "created_at" => $row['created_at'],
            "formatted_date" => date('d M Y', strtotime($row['created_at']))
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $formattedData
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
