<?php
// api/perpulangan/update_status.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $json = file_get_contents("php://input");
    $data = json_decode($json);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON Input");
    }

    if (!isset($data->permit_id) || !isset($data->status)) {
        throw new Exception("Data tidak lengkap (PermitID/Status hilang)");
    }

    $permit_id = (int) $data->permit_id;
    $status = (string) $data->status; // 'Kembali', 'Disetujui', 'Ditolak', etc.
    $now = date('Y-m-d H:i:s');

    // 1. Update status in boarding_permits
    $query = "UPDATE boarding_permits SET status = :status WHERE id = :permit_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':permit_id', $permit_id);
    
    if ($stmt->execute()) {
        // 2. If status is 'Kembali', insert into boarding_returns
        if ($status === 'Kembali') {
            // Fetch student_id first
            $stmtFetch = $conn->prepare("SELECT student_id FROM boarding_permits WHERE id = :pid LIMIT 1");
            $stmtFetch->execute([':pid' => $permit_id]);
            $sData = $stmtFetch->fetch(PDO::FETCH_ASSOC);

            if ($sData) {
                $student_id = $sData['student_id'];
                $returnDate = date('Y-m-d');
                $stmtReturn = $conn->prepare("INSERT INTO boarding_returns (student_id, return_date, status) VALUES (:sid, :rd, 'Sudah Kembali')");
                $stmtReturn->execute([':sid' => $student_id, ':rd' => $returnDate]);
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Status izin berhasil diperbarui"
        ]);
    } else {
        throw new Exception("Gagal memperbarui status ke database");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
