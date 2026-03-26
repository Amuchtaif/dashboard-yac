<?php
// api/meal_attendance/save_bulk.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $json = file_get_contents("php://input");
    $data = json_decode($json);

    if (!isset($data->meal_type) || !isset($data->date) || !isset($data->student_ids)) {
        echo json_encode(["success" => false, "message" => "Parameter tidak lengkap."]);
        exit();
    }

    $type = $data->meal_type;
    $date = $data->date;
    $ids = $data->student_ids; // Array of IDs
    $now = date('H:i:s');

    $conn->beginTransaction();

    $stmtInsert = $conn->prepare("INSERT IGNORE INTO meal_attendances (student_id, meal_type, date, check_time) VALUES (?, ?, ?, ?)");
    
    foreach ($ids as $id) {
        $stmtInsert->execute([$id, $type, $date, $now]);
    }

    $conn->commit();

    echo json_encode(["success" => true, "message" => count($ids) . " santri berhasil ditandai sudah makan."]);

} catch (PDOException $e) {
    if($conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
