<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';

    $query = "SELECT 
                s.id, 
                s.name, 
                s.code, 
                s.category as db_category,
                s.description,
                (SELECT COUNT(*) FROM class_schedules cs WHERE cs.subject_id = s.id) as total_sessions
              FROM subjects s
              WHERE (s.name LIKE :search OR s.code LIKE :search)";
    
    if ($category !== '' && $category !== 'Semua') {
        // Map UI category to DB category
        $db_cat = $category;
        if ($category === 'Religi') $db_cat = 'Diniyah';
        
        $query .= " AND s.category = :category";
    }

    $query .= " ORDER BY s.name ASC";

    $stmt = $db->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
    
    if ($category !== '' && $category !== 'Semua') {
        $db_cat = $category;
        if ($category === 'Religi') $db_cat = 'Diniyah';
        $stmt->bindParam(':category', $db_cat);
    }

    $stmt->execute();
    
    $subjects = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $db_category = $row['db_category'];
        $ui_category = $db_category;
        $icon = "book";
        $color = "blue";

        // Mapping for UI
        if ($db_category === 'Diniyah') {
            $ui_category = 'Religi';
            $icon = "mosque";
            $color = "purple";
        } elseif ($db_category === 'Akademik') {
            $ui_category = 'Akademik';
            $icon = "calculate";
            $color = "blue";
        } elseif ($db_category === 'Muatan Lokal') {
            $ui_category = 'Bahasa'; // Usually languages are here or in Umum
            $icon = "translate";
            $color = "green";
        } elseif ($db_category === 'Umum') {
            $ui_category = 'Umum';
            $icon = "school";
            $color = "orange";
        }
        
        // Estimate "Jam/Minggu"
        $hours_per_week = ceil($row['total_sessions'] / 20) + 2; 
        if ($hours_per_week > 6) $hours_per_week = 6;

        $subjects[] = [
            "id" => (int)$row['id'],
            "name" => $row['name'],
            "code" => $row['code'],
            "category" => $ui_category,
            "icon" => $icon,
            "color" => $color,
            "description" => $row['description'],
            "hours_per_week" => $hours_per_week . " Jam/Minggu"
        ];
    }

    // Prepare Categories for Chip Filter
    $ui_categories = ["Semua", "Akademik", "Religi", "Bahasa", "Umum"];

    echo json_encode([
        "success" => true,
        "categories" => $ui_categories,
        "data" => $subjects
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
