<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Cache-Control: public, max-age=60");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Get the latest/active academic year
    $query = "SELECT id, name, semester FROM academic_years ORDER BY start_date DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            "success" => true,
            "data" => [
                "academic_year_id" => (int)$row['id'],
                "academic_year_name" => $row['name'],
                "semester" => $row['semester']
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "No academic years found"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
