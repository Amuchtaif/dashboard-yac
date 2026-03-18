<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Check if table exists, if not create it
    $sql_create = "CREATE TABLE IF NOT EXISTS tahfidz_assessment_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql_create);

    // Check if empty
    $check_query = "SELECT COUNT(*) FROM tahfidz_assessment_types";
    $count = $db->query($check_query)->fetchColumn();

    if ($count == 0) {
        $sql_seed = "INSERT INTO tahfidz_assessment_types (name, is_active) VALUES 
            ('Bulanan (DB)', 1),
            ('Ujian (DB)', 1),
            ('Harian (DB)', 1),
            ('Tasmi (DB)', 1)";
        $db->exec($sql_seed);
        echo json_encode(["success" => true, "message" => "Table created and seeded with default values."]);
    } else {
        echo json_encode(["success" => true, "message" => "Table already has data."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
