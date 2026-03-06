<?php
// api/assignment/update_status.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $task_id = $_POST['task_id'] ?? null;
    $status = $_POST['status'] ?? null; // e.g. 'Sedang Dikerjakan'

    if (!$task_id || !$status) {
        echo json_encode(["status" => "error", "message" => "ID Tugas dan Status wajib disertakan."]);
        exit();
    }

    $sql = "UPDATE assignments SET 
            status = :status,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";

    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':status' => $status,
        ':id' => $task_id
    ]);

    if ($result) {
        echo json_encode(["status" => "success", "message" => "Status tugas berhasil diperbarui menjadi " . $status]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui status tugas."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal Server Error: " . $e->getMessage()]);
}
