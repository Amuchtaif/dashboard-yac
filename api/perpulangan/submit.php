<?php
// api/perpulangan/submit.php

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

    if (!isset($data->student_id) || !isset($data->category) || !isset($data->reason) || !isset($data->start_date) || !isset($data->end_date)) {
        throw new Exception("Data tidak lengkap (ID/Category/Reason/StartDate/EndDate hilang)");
    }

    $student_id = (int) $data->student_id;
    $category = (string) $data->category; // 'Izin', 'Sakit', 'Libur'
    $reason = (string) $data->reason;
    $start_date = (string) $data->start_date; // Expected 'Y-m-d H:i:s'
    $end_date = (string) $data->end_date;   // Expected 'Y-m-d H:i:s'
    $approved_by = isset($data->approved_by) ? (int) $data->approved_by : null;

    // Default status for new permit could be 'Disetujui' for immediate homecoming (based on UI in image)
    // or 'Menunggu' for approval workflow. Let's make it configurable or default to 'Disetujui'.
    $status = isset($data->status) ? (string) $data->status : 'Disetujui';

    $query = "
        INSERT INTO boarding_permits (student_id, category, reason, start_date, end_date, status, approved_by)
        VALUES (:student_id, :category, :reason, :start_date, :end_date, :status, :approved_by)
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':approved_by', $approved_by);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Izin perpulangan berhasil disimpan",
            "id" => $conn->lastInsertId()
        ]);
    } else {
        throw new Exception("Gagal menyimpan data ke database");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
