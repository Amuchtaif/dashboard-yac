<?php
// api/assignment/update_progress.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $task_id = $_POST['task_id'] ?? null;
    $progress = $_POST['progress'] ?? null; // e.g. 0 to 100

    if (!$task_id || $progress === null) {
        echo json_encode(["status" => "error", "message" => "ID Tugas dan Nilai Progres wajib disertakan."]);
        exit();
    }

    // Ambil data tugas saat ini untuk pengecekan status
    $checkSql = "SELECT status FROM assignments WHERE id = :id";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([':id' => $task_id]);
    $currentTask = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentTask) {
        echo json_encode(["status" => "error", "message" => "Tugas tidak ditemukan."]);
        exit();
    }

    // Tentukan status otomatis berdasarkan progres
    $newStatus = $currentTask['status'];
    if ($progress >= 100) {
        $newStatus = 'Selesai';
    } else if ($progress > 0) {
        // Jika progres sudah jalan, status minimal "Sedang Dikerjakan"
        if ($currentTask['status'] === 'Belum Dimulai') {
            $newStatus = 'Sedang Dikerjakan';
        }
    } else if ($progress == 0) {
        // Jika progres 0, status kembali ke "Belum Dimulai" (opsional)
        if ($currentTask['status'] !== 'Selesai') {
            $newStatus = 'Belum Dimulai';
        }
    }

    $sql = "UPDATE assignments SET 
            progress = :progress,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";

    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':progress' => $progress,
        ':status' => $newStatus,
        ':id' => $task_id
    ]);

    if ($result) {
        echo json_encode([
            "status" => "success", 
            "message" => "Progres tugas berhasil diperbarui menjadi " . $progress . "%",
            "data" => [
                "progress" => (int)$progress,
                "status" => $newStatus
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui progres tugas."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal Server Error: " . $e->getMessage()]);
}
