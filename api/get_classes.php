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
    
    $query = "SELECT 
                gl.id, 
                gl.name as class_name, 
                gl.category as unit_name,
                e.full_name as teacher_name,
                (SELECT COUNT(*) FROM students s WHERE s.kelas = gl.name) as student_count
              FROM grade_levels gl
              LEFT JOIN employees e ON gl.teacher_id = e.id
              WHERE gl.name LIKE :search OR gl.category LIKE :search
              ORDER BY gl.category ASC, gl.name ASC";

    $stmt = $db->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
    $stmt->execute();
    
    $classes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $classes[] = [
            "id" => (int)$row['id'],
            "class_name" => $row['class_name'],
            "unit_name" => $row['unit_name'],
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
