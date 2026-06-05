<?php
// api/action_permit.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

// Helper Function untuk kirim respon JSON & STOP script
function sendResponse($success, $message)
{
    ob_clean();
    echo json_encode(["success" => $success, "message" => $message]);
    exit();
}

try {
    // Koneksi Database
    $database = new Database();
    $conn = $database->getConnection();

    // Cek Method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, "Method not allowed");
    }

    // 2. Ambil Input JSON dari Flutter
    $json = file_get_contents("php://input");
    $data = json_decode($json);

    // Cek apakah JSON valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, "Invalid JSON Input");
    }

    // Input: permit_id, approver_id (ID Pimpinan), action (approve/reject), note
    if (!isset($data->permit_id) || !isset($data->approver_id) || !isset($data->action)) {
        sendResponse(false, "Data tidak lengkap (ID/Approver/Action hilang)");
    }

    $permit_id = $data->permit_id;
    $approver_id = $data->approver_id;
    $action = $data->action;
    $note = isset($data->note) ? $data->note : null;

    // Tentukan Status Baru
    $newStatus = ($action == 'approve') ? 'Approved' : 'Rejected';
    $now = date('Y-m-d H:i:s');

    // 3. SECURE VALIDATION (Strict Approver Check)
    $stmtCheck = $conn->prepare("
        SELECT p.approver_id, p.employee_id, p.status, pos.level as applicant_level
        FROM permits p
        JOIN employees e ON p.employee_id = e.id
        JOIN positions pos ON e.position_id = pos.id
        WHERE p.id = :pid LIMIT 1
    ");
    $stmtCheck->execute([':pid' => $permit_id]);
    $pData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$pData)
        sendResponse(false, "Izin tidak ditemukan");
    if ($pData['status'] !== 'Pending')
        sendResponse(false, "Izin ini sudah diproses.");

    // Fetch Current Approver Level
    $stmtU = $conn->prepare("SELECT p.level FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = :aid");
    $stmtU->execute([':aid' => $approver_id]);
    $uLevel = (int) $stmtU->fetchColumn();

    // Check Authorization
    $isAuthorized = false;
    if ($pData['approver_id'] == $approver_id) {
        $isAuthorized = true;
    } elseif ($uLevel === 1 && $pData['applicant_level'] == 2) {
        $isAuthorized = true;
    }

    if (!$isAuthorized) {
        sendResponse(false, "Anda tidak memiliki wewenang untuk menyetujui izin ini.");
    }

    // Prevention: Cannot approve own permit
    if ($pData['employee_id'] == $approver_id) {
        sendResponse(false, "Anda tidak dapat menyetujui izin Anda sendiri.");
    }

    // 4. EKSEKUSI UPDATE DATABASE
    $sql = "UPDATE permits SET 
            status = :status, 
            approved_by = :aid, 
            approved_at = :now,
            rejection_note = :note
            WHERE id = :pid";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $newStatus);
    $stmt->bindParam(':aid', $approver_id);
    $stmt->bindParam(':now', $now);
    $stmt->bindParam(':note', $note);
    $stmt->bindParam(':pid', $permit_id);

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            // --- NOTIFICATION LOGIC ---
            if (!function_exists('logFCM')) {
                function logFCM($msg)
                {
                    $logFile = __DIR__ . '/fcm_debug.log';
                    $formattedMsg = date('Y-m-d H:i:s') . " [ACTION] - " . $msg . "\n";
                    @file_put_contents($logFile, $formattedMsg, FILE_APPEND);
                    if (!is_writable($logFile) || !file_exists($logFile)) {
                        error_log("[FCM ACTION] " . $msg);
                    }
                }
            }

            $employee_id = $pData['employee_id'];
            $stmtToken = $conn->prepare("SELECT fcm_token FROM employees WHERE id = :eid LIMIT 1");
            $stmtToken->execute([':eid' => $employee_id]);
            $tokenData = $stmtToken->fetch(PDO::FETCH_ASSOC);

            if ($tokenData && !empty($tokenData['fcm_token'])) {
                $targetToken = $tokenData['fcm_token'];
                try {
                    require_once 'AccessToken.php';
                    $serviceAccountPath = __DIR__ . '/service-account.json';

                    if (file_exists($serviceAccountPath)) {
                        $googleToken = new GoogleAccessToken($serviceAccountPath);
                        $accessToken = $googleToken->getToken();

                        if ($accessToken) {
                            $credentials = json_decode(file_get_contents($serviceAccountPath), true);
                            $projectId = $credentials['project_id'];
                            $fcmUrl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

                            $payloadData = [
                                'message' => [
                                    'token' => $targetToken,
                                    'notification' => [
                                        'title' => 'Status Izin Diperbarui',
                                        'body' => "Pengajuan izin Anda telah: $newStatus oleh atasan."
                                    ],
                                    'android' => [
                                        'priority' => 'HIGH',
                                        'notification' => [
                                            'channel_id' => 'high_importance_channel',
                                            'sound' => 'default'
                                        ]
                                    ],
                                    'data' => [
                                        'screen' => 'permit',
                                        'permit_id' => (string) $permit_id
                                    ]
                                ]
                            ];

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $fcmUrl);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                "Authorization: Bearer $accessToken",
                                "Content-Type: application/json"
                            ]);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadData));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);
                            if ($response === false) {
                                logFCM("Curl error: " . curl_error($ch));
                            } else {
                                logFCM("FCM response: " . $response);
                            }
                            curl_close($ch);
                        } else {
                            logFCM("GoogleAccessToken returned null token");
                        }
                    } else {
                        logFCM("Service account file not found at: " . $serviceAccountPath);
                    }
                } catch (Exception $e) {
                    logFCM("Exception: " . $e->getMessage());
                }
            } else {
                logFCM("Token empty or tokenData not found for employee ID: " . $employee_id);
            }
            sendResponse(true, "Berhasil memproses izin: " . $newStatus);
        } else {
            sendResponse(true, "Data disimpan (Tidak ada perubahan status)");
        }
    } else {
        $errorInfo = $stmt->errorInfo();
        sendResponse(false, "Gagal update database: " . $errorInfo[2]);
    }

} catch (Exception $e) {
    sendResponse(false, "System Error: " . $e->getMessage());
}

?>