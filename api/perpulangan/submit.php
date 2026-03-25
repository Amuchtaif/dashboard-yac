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
    $musrif_id = $data->musrif_id ?? $data->user_id ?? $data->employee_id ?? $data->staff_id ?? $data->id ?? null;
    if (!$musrif_id) {
        error_log("PERPULANGAN SUBMIT: musrif_id missing. Input: " . $json);
    }
    $approved_by = isset($data->approved_by) ? (int) $data->approved_by : null;

    // Default status for new permit is 'Pending' for approval workflow
    $status = 'Pending';

    $query = "
        INSERT INTO boarding_permits (student_id, musrif_id, category, reason, start_date, end_date, status, approved_by)
        VALUES (:student_id, :musrif_id, :category, :reason, :start_date, :end_date, :status, :approved_by)
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':musrif_id', $musrif_id);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':approved_by', $approved_by);

    if ($stmt->execute()) {
        $newPermitId = $conn->lastInsertId();

        // Send FCM Notification to Mudir Kepengasuhan
        try {
            require_once __DIR__ . '/../../config/fcm_helper.php';
            $fcm = new FcmHelper();

            // Find Mudir Kepengasuhan (searching by position name or position_id 1)
            $stmtMudir = $conn->prepare("
                SELECT e.fcm_token 
                FROM employees e
                JOIN positions p ON e.position_id = p.id
                WHERE ( (e.position_id IN (1, 2, 3) AND e.unit_id = 16)
                   OR (e.position_id = 1)
                   OR p.name LIKE '%Mudir%'
                   OR p.name LIKE '%Kepengasuhan%' )
                AND e.fcm_token IS NOT NULL 
                AND e.fcm_token != ''
            ");
            $stmtMudir->execute();
            $tokens = $stmtMudir->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($tokens)) {
                // Get student name for notification
                $stmtStudent = $conn->prepare("SELECT nama_siswa FROM students WHERE id = :sid");
                $stmtStudent->execute([':sid' => $student_id]);
                $studentName = $stmtStudent->fetchColumn();

                // Get musrif name for notification
                $musrifName = "Musrif";
                $musrif_id = $data->musrif_id ?? $data->user_id ?? null;
                if ($musrif_id) {
                    $stmtMusrif = $conn->prepare("SELECT full_name FROM employees WHERE id = :mid");
                    $stmtMusrif->execute([':mid' => $musrif_id]);
                    $musrifName = $stmtMusrif->fetchColumn() ?: "Musrif";
                }

                $title = "Izin Santri Baru";
                $body = "{$musrifName} mengajukan izin untuk {$studentName}. Silakan tinjau dan berikan persetujuan.";
                $notifData = [
                    "screen" => "izin_santri",
                    "id" => (string)$newPermitId,
                    "click_action" => "FLUTTER_NOTIFICATION_CLICK"
                ];

                $successCount = 0;
                foreach ($tokens as $token) {
                    $result = $fcm->sendNotification($token, $title, $body, $notifData);
                    if ($result && !isset($result['error'])) {
                        $successCount++;
                    } else {
                        error_log("FCM Send Error for Token: " . substr($token, 0, 10) . "... Error: " . json_encode($result));
                    }
                }
                
                // Optional: Log success
                $msg = "[" . date('Y-m-d H:i:s') . "] PERPULANGAN: FCM sent {$successCount} notifications for permit {$newPermitId}\n";
                file_put_contents(__DIR__ . '/../fcm_debug.log', $msg, FILE_APPEND);
            } else {
                $msg = "[" . date('Y-m-d H:i:s') . "] PERPULANGAN: No Mudir tokens found for permit {$newPermitId}\n";
                file_put_contents(__DIR__ . '/../fcm_debug.log', $msg, FILE_APPEND);
            }
        } catch (Exception $e) {
            // Silently fail notification, don't break the submission
            $msg = "[" . date('Y-m-d H:i:s') . "] PERPULANGAN: Exception - " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/../fcm_debug.log', $msg, FILE_APPEND);
        }

        echo json_encode([
            "success" => true,
            "message" => "Izin perpulangan berhasil diajukan dan menunggu persetujuan Mudir",
            "id" => (string)$newPermitId
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
