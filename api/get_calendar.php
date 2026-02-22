<?php
// api/get_calendar.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    
    // Fetch all events for the year
    $query = "SELECT id, title, description, start_date, end_date, category, is_holiday, color 
              FROM academic_calendar 
              WHERE YEAR(start_date) = :year OR YEAR(end_date) = :year
              ORDER BY start_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':year', $year);
    $stmt->execute();
    
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            "id" => (int)$row['id'],
            "title" => $row['title'],
            "description" => $row['description'],
            "start_date" => $row['start_date'],
            "end_date" => $row['end_date'],
            "category" => $row['category'],
            "is_holiday" => (bool)$row['is_holiday'],
            "color" => $row['color']
        ];
    }

    echo json_encode([
        "success" => true,
        "year" => $year,
        "data" => $events
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
