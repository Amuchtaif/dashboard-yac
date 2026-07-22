<?php
// api/tahfidz/delete_target_hafalan.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if ($data && !empty($data->id)) {
    try {
        $target_id = intval($data->id);

        // Fetch target info before deleting
        $old_stmt = $db->prepare("
            SELECT th.target_juz, gl.name as class_name, eu.name as unit_name
            FROM target_hafalan th
            LEFT JOIN grade_levels gl ON th.kelas_id = gl.id
            LEFT JOIN education_units eu ON th.unit_id = eu.id
            WHERE th.id = ? LIMIT 1
        ");
        $old_stmt->execute([$target_id]);
        $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

        $kelas_name = $old_data['class_name'] ?? "ID Kelas";
        $unit_name = $old_data['unit_name'] ?? "ID Unit";
        $target_juz = $old_data['target_juz'] ?? "0";

        $query = "DELETE FROM target_hafalan WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $target_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            Logger::activity(
                'Tahfidz',
                'DELETE_TARGET_HAFALAN',
                "Menghapus target hafalan kelas '$kelas_name' ($unit_name): $target_juz Juz",
                [
                    'table' => 'target_hafalan',
                    'record_id' => $target_id,
                    'old_data' => [
                        'kelas' => $kelas_name,
                        'unit' => $unit_name,
                        'target_juz' => $target_juz
                    ]
                ]
            );

            echo json_encode(["success" => true, "message" => "Target hafalan berhasil dihapus."]);
        } else {
            echo json_encode(["success" => false, "message" => "Gagal menghapus target hafalan."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error database: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Data tidak lengkap. ID wajib dikirim."]);
}
?>
