<?php
// api/action_permit.php

// 1. NYALAKAN DEBUGGING (Agar error terlihat di Flutter)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

// Helper Function untuk kirim respon JSON & STOP script
function sendResponse($success, $message)
{
    echo json_encode(["success" => $success, "message" => $message]);
    exit();
}

// Cek Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Method not allowed");
}

try {
    // Koneksi Database
    $database = new Database();
    $conn = $database->getConnection();

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
        // Mudir (1) is authorized to approve any Kabid (2) request
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
    // Note: We remove approver_id from WHERE to allow shared level 1 approval
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
        // Cek apakah ada baris yang ter-update
        if ($stmt->rowCount() > 0) {

            // --- NOTIFICATION LOGIC ---
            // Kirim Notif ke Pemohon (Employee)
            // 1. Ambil employee_id dari permit_id
            $employee_id = $pData['employee_id'];

            // 2. Ambil FCM Token Employee
            $stmtToken = $conn->prepare("SELECT fcm_token FROM employees WHERE id = :eid LIMIT 1");
            $stmtToken->execute([':eid' => $employee_id]);
            $tokenData = $stmtToken->fetch(PDO::FETCH_ASSOC);

            if ($tokenData && !empty($tokenData['fcm_token'])) {
                $targetToken = $tokenData['fcm_token'];

                // --- FCM V1 using GoogleAccessToken helper ---
                try {
                    require_once 'AccessToken.php';

                    // Log helper
                    function logPermitAction($msg)
                    {
                        file_put_contents('fcm_debug.log', date('Y-m-d H:i:s') . " [PERMIT_ACTION] - " . $msg . "\n", FILE_APPEND);
                    }

                    $serviceAccountPath = 'service-account.json';

                    if (file_exists($serviceAccountPath)) {
                        $googleToken = new GoogleAccessToken($serviceAccountPath);
                        $accessToken = $googleToken->getToken();

                        if ($accessToken) {
                            $credentials = json_decode(file_get_contents($serviceAccountPath), true);
                            $projectId = $credentials['project_id'];

                            // Send Notification to Employee
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
                                            'sound' => 'default',
                                            'default_sound' => true
                                        ]
                                    ],
                                    'data' => [
                                        'screen' => 'permit',
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
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
                            $fcmResult = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);

                            if ($httpCode === 200) {
                                logPermitAction("FCM sent to employee ID $employee_id ($newStatus)");
                            } else {
                                logPermitAction("FCM failed for employee ID $employee_id: $fcmResult");
                            }
                        } else {
                            logPermitAction("Failed to get access token");
                        }
                    } else {
                        logPermitAction("Service account not found");
                    }
                } catch (Exception $e) {
                    // Silent fail (allow API to succeed even if notif fails)
                }
            }
            // --- END NOTE ---

            sendResponse(true, "Berhasil memproses izin: " . $newStatus);
        } else {
            // Jika rowCount 0, berarti ID izin tidak ditemukan atau data tidak berubah
            sendResponse(true, "Data disimpan (Tidak ada perubahan status)");
        }
    } else {
        // Ambil error info SQL
        $errorInfo = $stmt->errorInfo();
        sendResponse(false, "Gagal update database: " . $errorInfo[2]);
    }

} catch (PDOException $e) {
    sendResponse(false, "Database Exception: " . $e->getMessage());
} catch (Exception $e) {
    sendResponse(false, "System Error: " . $e->getMessage());
}
?>