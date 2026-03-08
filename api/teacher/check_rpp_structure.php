<?php
header('Content-Type: application/json');
require '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->query("SHOW TABLES LIKE 'lesson_plans'");
    $exists = $stmt->rowCount() > 0;
    
    if($exists) {
        $stmt2 = $conn->query("DESCRIBE lesson_plans");
        echo json_encode($stmt2->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode(["message" => "Table lesson_plans does not exist"]);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
