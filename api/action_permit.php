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

    // 3. EKSEKUSI UPDATE DATABASE
    // Kita update: status, approved_by (pimpinan yg klik), approved_at, rejection_note
    // Syarat WHERE: id harus cocok DAN approver_id harus cocok (security check)

    $sql = "UPDATE permits SET 
            status = :status, 
            approved_by = :aid, 
            approved_at = :now,
            rejection_note = :note
            WHERE id = :pid AND approver_id = :aid";
    // Note: Saya hapus 'AND approver_id = :aid' di WHERE agar lebih fleksibel 
    // jika seandainya admin override, tapi idealnya tetap ada. 
    // Untuk debugging, kita pakai WHERE id saja dulu.

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
            $stmtEmp = $conn->prepare("SELECT employee_id, permit_type FROM permits WHERE id = :pid LIMIT 1");
            $stmtEmp->execute([':pid' => $permit_id]);
            $permData = $stmtEmp->fetch(PDO::FETCH_ASSOC);

            if ($permData) {
                $employee_id = $permData['employee_id'];
                $pType = $permData['permit_type'];

                // 2. Ambil FCM Token Employee
                $stmtToken = $conn->prepare("SELECT fcm_token FROM employees WHERE id = :eid LIMIT 1");
                $stmtToken->execute([':eid' => $employee_id]);
                $tokenData = $stmtToken->fetch(PDO::FETCH_ASSOC);

                if ($tokenData && !empty($tokenData['fcm_token'])) {
                    $targetToken = $tokenData['fcm_token'];

                    // --- NATIVE PHP FCM V1 LOGIC (WITHOUT COMPOSER) ---
                    try {
                        $serviceAccountPath = 'service-account.json';

                        if (file_exists($serviceAccountPath)) {
                            $credentials = json_decode(file_get_contents($serviceAccountPath), true);
                            $clientEmail = $credentials['client_email'];
                            $privateKey = $credentials['private_key'];
                            $projectId = $credentials['project_id'];

                            // Helper: Base64Url Encode
                            if (!function_exists('base64UrlEncode')) {
                                function base64UrlEncode($data)
                                {
                                    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
                                }
                            }

                            // A. Generate JWT
                            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
                            $now = time();
                            $payload = json_encode([
                                'iss' => $clientEmail,
                                'sub' => $clientEmail,
                                'aud' => 'https://oauth2.googleapis.com/token',
                                'iat' => $now,
                                'exp' => $now + 3600,
                                'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
                            ]);

                            $base64Header = base64UrlEncode($header);
                            $base64Payload = base64UrlEncode($payload);
                            $signatureInput = $base64Header . "." . $base64Payload;

                            $signature = '';
                            openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
                            $jwt = $signatureInput . "." . base64UrlEncode($signature);

                            // B. Exchange JWT for Google Access Token
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                                'assertion' => $jwt
                            ]));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);
                            curl_close($ch);

                            $tokenResponse = json_decode($response, true);

                            if (isset($tokenResponse['access_token'])) {
                                $accessToken = $tokenResponse['access_token'];

                                // C. Send Notification to Employee
                                $fcmUrl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

                                $payloadData = [
                                    'message' => [
                                        'token' => $targetToken,
                                        'notification' => [
                                            'title' => 'Status Izin Diperbarui',
                                            'body' => "Pengajuan izin Anda telah: $newStatus oleh atasan."
                                        ],
                                        'data' => [
                                            'screen' => 'history',
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
                                curl_exec($ch); // Send and forget
                                curl_close($ch);
                            }
                        }
                    } catch (Exception $e) {
                        // Silent fail (allow API to succeed even if notif fails)
                    }
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