<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Search query
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Fetch Active Academic Year
    $active_year_id = $db->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    if (!$active_year_id) {
        $active_year_id = 1;
    }
    
    $query = "SELECT 
                gl.id, 
                gl.name as class_name, 
                eu.name as unit_name,
                e.full_name as teacher_name,
                (SELECT COUNT(*) 
                 FROM student_class_history sch 
                 JOIN students s ON sch.student_id = s.id
                 WHERE sch.class_id = gl.id 
                   AND sch.academic_year_id = :active_year_id 
                   AND sch.status = 'ACTIVE'
                   AND s.status = 'Aktif'
                ) as student_count
              FROM grade_levels gl
              LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
              LEFT JOIN employees e ON gl.teacher_id = e.id
              WHERE gl.is_active = 1 
                AND (gl.name LIKE :search OR eu.name LIKE :search)
              ORDER BY eu.name ASC, gl.name ASC";

    $stmt = $db->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
    $stmt->bindParam(':active_year_id', $active_year_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $classes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $classes[] = [
            "id" => (int)$row['id'],
            "class_name" => $row['class_name'],
            "unit_name" => $row['unit_name'] ?? '-',
            "teacher_name" => $row['teacher_name'] ?? 'Belum Ditentukan',
            "student_count" => (int)$row['student_count'],
            "room" => "Ruang " . $row['id'] // Fallback if no room field exists
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $classes
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
