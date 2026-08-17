<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/database.php';

try {
    $employee_id = $_GET['employee_id'] ?? null;
    if (!$employee_id) {
        throw new Exception("Employee ID is required");
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get Units from schedules
    $q_units = "
        SELECT DISTINCT eu.id, eu.name 
        FROM class_schedules cs
        JOIN grade_levels gl ON cs.grade_level_id = gl.id
        JOIN education_units eu ON gl.education_unit_id = eu.id
        WHERE cs.employee_id = ? AND cs.is_active = 1
        ORDER BY eu.name ASC
    ";
    $stmt1 = $db->prepare($q_units);
    $stmt1->execute([$employee_id]);
    $units = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // Get Classes from schedules
    $q_classes = "
        SELECT DISTINCT gl.id, gl.name, gl.education_unit_id
        FROM class_schedules cs
        JOIN grade_levels gl ON cs.grade_level_id = gl.id
        WHERE cs.employee_id = ? AND cs.is_active = 1
        ORDER BY gl.name ASC
    ";
    $stmt2 = $db->prepare($q_classes);
    $stmt2->execute([$employee_id]);
    $classes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Get Subjects from schedules
    $q_subjects = "
        SELECT DISTINCT s.id, s.name, cs.grade_level_id
        FROM class_schedules cs
        JOIN subjects s ON cs.subject_id = s.id
        WHERE cs.employee_id = ? AND cs.is_active = 1
        ORDER BY s.name ASC
    ";
    $stmt3 = $db->prepare($q_subjects);
    $stmt3->execute([$employee_id]);
    $subjects = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => [
            "units" => $units,
            "classes" => $classes,
            "subjects" => $subjects
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
