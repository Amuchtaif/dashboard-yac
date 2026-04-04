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
    $musyrif_id = $data->musyrif_id ?? null;
    $now = date('H:i:s');

    // 1. Check if ANY student in the same room is already marked by someone ELSE for this date/meal_type
    // We get room_id from the first student in the list
    if ($musyrif_id && count($ids) > 0) {
        $first_student = $ids[0];
        $room_stmt = $conn->prepare("SELECT room_id FROM boarding_room_members WHERE student_id = ? LIMIT 1");
        $room_stmt->execute([$first_student]);
        $room = $room_stmt->fetch(PDO::FETCH_ASSOC);

        if ($room) {
            $check_sql = "
                SELECT created_by, (SELECT full_name FROM employees WHERE id = ma.created_by) as creator_name
                FROM meal_attendances ma
                JOIN boarding_room_members brm ON ma.student_id = brm.student_id
                WHERE brm.room_id = ? AND ma.date = ? AND ma.meal_type = ? AND ma.created_by IS NOT NULL AND ma.created_by != ?
                LIMIT 1
            ";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([$room['room_id'], $date, $type, $musyrif_id]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                echo json_encode([
                    "success" => false, 
                    "message" => "Asrama ini sudah diabsen oleh Musyrif " . ($existing['creator_name'] ?? 'lain') . ". Anda tidak diperbolehkan menginput absen lagi."
                ]);
                exit;
            }
        }
    }

    $conn->beginTransaction();

    // Use REPLACE instead of INSERT IGNORE to allow the same person to update their results
    $stmtInsert = $conn->prepare("
        INSERT INTO meal_attendances (student_id, meal_type, date, check_time, created_by) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE check_time = VALUES(check_time), created_by = VALUES(created_by)
    ");
    
    foreach ($ids as $id) {
        $stmtInsert->execute([$id, $type, $date, $now, $musyrif_id]);
    }

    $conn->commit();

    echo json_encode(["success" => true, "message" => count($ids) . " santri berhasil ditandai sudah makan."]);

} catch (PDOException $e) {
    if($conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
