<?php
// api/meal_attendance/delete.php
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

    if (!isset($data->id)) {
        echo json_encode(["success" => false, "message" => "ID tidak ditemukan."]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM meal_attendances WHERE id = ?");
    if ($stmt->execute([$data->id])) {
        echo json_encode(["success" => true, "message" => "Data absensi makan berhasil dihapus."]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal menghapus data."]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
