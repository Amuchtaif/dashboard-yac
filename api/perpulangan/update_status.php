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
        // Fetch permit details for notification
        $stmtDetails = $conn->prepare("
            SELECT bp.musrif_id, s.nama_siswa, bp.status
            FROM boarding_permits bp
            JOIN students s ON bp.student_id = s.id
            WHERE bp.id = :pid LIMIT 1
        ");
        $stmtDetails->execute([':pid' => $permit_id]);
        $pData = $stmtDetails->fetch(PDO::FETCH_ASSOC);

        if ($pData && $pData['musrif_id'] && in_array($status, ['Disetujui', 'Ditolak'])) {
            // Send FCM Notification to Musrif (only for decision statuses from Mudir)
            try {
                require_once __DIR__ . '/../../config/fcm_helper.php';
                $fcm = new FcmHelper();

                $stmtMusrif = $conn->prepare("SELECT fcm_token FROM employees WHERE id = :mid AND fcm_token IS NOT NULL AND fcm_token != ''");
                $stmtMusrif->execute([':mid' => $pData['musrif_id']]);
                $musrifToken = $stmtMusrif->fetchColumn();

                if ($musrifToken) {
                    $status_upper = strtoupper($status);
                    $title = "Izin Santri " . $status;
                    $body = "Izin untuk " . $pData['nama_siswa'] . " telah " . $status_upper . " oleh Mudir.";
                    $notifData = [
                        "screen" => "izin_santri",
                        "id" => (string)$permit_id,
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK"
                    ];
                    $result = $fcm->sendNotification($musrifToken, $title, $body, $notifData);
                    
                    // Log to fcm_debug.log
                    $logMsg = "[" . date('Y-m-d H:i:s') . "] STATUS_UPDATE: FCM sent to Musrif ID {$pData['musrif_id']} for permit {$permit_id}. Result: " . (isset($result['name']) ? "SUCCESS" : "FAILED") . "\n";
                    file_put_contents(__DIR__ . '/../../api/fcm_debug.log', $logMsg, FILE_APPEND);
                }
            } catch (Exception $e) {
                $errLog = "[" . date('Y-m-d H:i:s') . "] STATUS_UPDATE: Exception - " . $e->getMessage() . "\n";
                file_put_contents(__DIR__ . '/../../api/fcm_debug.log', $errLog, FILE_APPEND);
            }
        }

        // 2. If status is 'Kembali', insert into boarding_returns
        if ($status === 'Kembali') {
            $student_id = $pData['student_id'] ?? null;
            if (!$student_id) {
                $stmtFetch = $conn->prepare("SELECT student_id FROM boarding_permits WHERE id = :pid LIMIT 1");
                $stmtFetch->execute([':pid' => $permit_id]);
                $student_id = $stmtFetch->fetchColumn();
            }
            
            if ($student_id) {
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
