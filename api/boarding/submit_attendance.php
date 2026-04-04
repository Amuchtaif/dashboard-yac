<?php
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
    $data = json_decode($json, true);

    if (!$data || !isset($data['room_id']) || !isset($data['date']) || !isset($data['attendance'])) {
        echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
        exit;
    }

    $room_id = $data['room_id'];
    $date = $data['date'];
    $attendance_list = $data['attendance'];
    $created_by = (isset($data['created_by']) && $data['created_by'] !== '') ? $data['created_by'] : null;

    // 1. Check if ANY attendance has already been filled for this room and date by someone else
    if ($created_by) {
        $check_sql = "
            SELECT created_by, (SELECT full_name FROM employees WHERE id = boarding_attendances.created_by) as creator_name
            FROM boarding_attendances 
            WHERE room_id = ? AND date = ? AND created_by IS NOT NULL AND created_by != ?
            LIMIT 1
        ";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$room_id, $date, $created_by]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                "success" => false, 
                "message" => "Asrama ini sudah diabsen oleh Musyrif " . ($existing['creator_name'] ?? 'lain') . ". Anda tidak diperbolehkan menginput absen lagi."
            ]);
            exit;
        }
    }

    // Define valid statuses to match the ENUM in DB
    $valid_statuses = ['Hadir', 'Sakit', 'Izin', 'Alpha'];

    $conn->beginTransaction();

    $upsert_sql = "
        INSERT INTO boarding_attendances (room_id, student_id, date, status, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status), 
            notes = VALUES(notes),
            created_by = VALUES(created_by)
    ";
    $stmt = $conn->prepare($upsert_sql);

    $saved_count = 0;
    foreach ($attendance_list as $student_id => $item) {
        // Handle both Array of Objects [{"student_id": 1, "status": "Hadir"}] 
        // and Associative Array {"1": {"status": "Hadir"}}
        $s_id = isset($item['student_id']) ? $item['student_id'] : $student_id;
        
        // Critical Fix: Validate student_id must be present and > 0
        if (empty($s_id) || $s_id <= 0) {
            continue; 
        }

        $status = isset($item['status']) ? $item['status'] : 'Alpha';
        
        // Normalize status: trim and capitalize first letter to match Enum
        $status = ucfirst(strtolower(trim($status)));
        // Note: 'Alfa' often used in Flutter, but DB has 'Alpha'
        if ($status === 'Alfa') $status = 'Alpha';
        
        if (!in_array($status, $valid_statuses)) {
            $status = 'Alpha';
        }

        $notes = isset($item['notes']) ? $item['notes'] : (isset($item['keterangan']) ? $item['keterangan'] : '');
        
        try {
            $stmt->execute([$room_id, $s_id, $date, $status, $notes, $created_by]);
            $saved_count++;
        } catch (PDOException $e) {
            throw new Exception("Gagal menyimpan ID Santri $s_id: " . $e->getMessage());
        }
    }

    if ($saved_count === 0) {
        throw new Exception("Tidak ada data santri yang valid untuk disimpan (ID Santri kosong atau 0)");
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Berhasil menyimpan $saved_count data absensi"
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    // Return fail instead of success when something goes wrong
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>
