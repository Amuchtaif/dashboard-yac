<?php
// api/assignment/create.php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");
date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

// Debug Logger
function logAssignment($msg) {
    file_put_contents(__DIR__ . '/../fcm_debug.log', date('Y-m-d H:i:s') . " [ASSIGNMENT] - " . $msg . "\n", FILE_APPEND);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        exit();
    }

    // Support both JSON body and Form Data
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    $title = $input['title'] ?? $_POST['title'] ?? '';
    $description = $input['description'] ?? $_POST['description'] ?? '';
    $special_instructions = $input['special_instructions'] ?? $_POST['special_instructions'] ?? '';
    $priority = $input['priority'] ?? $_POST['priority'] ?? 'Rutin';
    $due_date = $input['due_date'] ?? $_POST['due_date'] ?? null;
    $created_by = $input['created_by'] ?? $_POST['created_by'] ?? null;
    $assigned_to = $input['assigned_to'] ?? $_POST['assigned_to'] ?? null;

    if (empty($title) || empty($created_by) || empty($assigned_to)) {
        echo json_encode(["status" => "error", "message" => "Judul, Pembuat, dan Penerima wajib diisi."]);
        exit();
    }

    // ========== 1. AUTO-MIGRATION: Ensure notifications table exists ==========
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `body` TEXT,
            `type` VARCHAR(50) DEFAULT 'general',
            `reference_id` INT DEFAULT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Table might already exist
    }

    // Handle File Attachment Upload
    $attachmentName = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadFileDir = '../../uploads/assignments/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        $fileExtension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'zip', 'jpg', 'jpeg', 'png'];
        if (in_array($fileExtension, $allowed)) {
            $newFileName = time() . '_task_' . rand(1000, 9999) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest_path)) {
                $attachmentName = $newFileName;
            }
        }
    }

    // ========== 2. INSERT ASSIGNMENT ==========
    $sql = "INSERT INTO assignments (title, description, special_instructions, priority, due_date, created_by, assigned_to, attachment_path) 
            VALUES (:title, :description, :special_instructions, :priority, :due_date, :created_by, :assigned_to, :attachment_path)";

    $stmt = $db->prepare($sql);
    $params = [
        ':title' => $title,
        ':description' => $description,
        ':special_instructions' => $special_instructions,
        ':priority' => $priority,
        ':due_date' => $due_date,
        ':created_by' => $created_by,
        ':assigned_to' => $assigned_to,
        ':attachment_path' => $attachmentName
    ];

    if ($stmt->execute($params)) {
        $assignment_id = $db->lastInsertId();

        // Get creator name for notification
        $stmtName = $db->prepare("SELECT full_name FROM employees WHERE id = :id LIMIT 1");
        $stmtName->execute([':id' => $created_by]);
        $creatorName = $stmtName->fetchColumn() ?: 'Seseorang';

        $notifTitle = 'Tugas Baru';
        $notifBody = "Anda mendapat tugas: $title";

        // ========== 3. SAVE TO NOTIFICATIONS TABLE ==========
        try {
            $sqlNotif = "INSERT INTO notifications (user_id, title, body, type, reference_id)
                         VALUES (:user_id, :title, :body, 'assignment', :reference_id)";
            $stmtNotif = $db->prepare($sqlNotif);
            $stmtNotif->execute([
                ':user_id' => $assigned_to,
                ':title' => $notifTitle,
                ':body' => $notifBody,
                ':reference_id' => $assignment_id
            ]);
            $db_notification_id = $db->lastInsertId();
            logAssignment("Notification saved to DB for user $assigned_to, assignment $assignment_id, ID: $db_notification_id");
        } catch (Exception $e) {
            logAssignment("Failed to save notification to DB: " . $e->getMessage());
        }

        // ========== 4. SEND FCM PUSH NOTIFICATION ==========
        $fcmSent = false;

        try {
            // Get assignee's FCM token
            $stmtToken = $db->prepare("SELECT fcm_token FROM employees WHERE id = :id AND fcm_token IS NOT NULL AND fcm_token != '' LIMIT 1");
            $stmtToken->execute([':id' => $assigned_to]);
            $tokenRow = $stmtToken->fetch(PDO::FETCH_ASSOC);

            if ($tokenRow && !empty($tokenRow['fcm_token'])) {
                $targetToken = $tokenRow['fcm_token'];
                logAssignment("FCM token found for user $assigned_to: " . substr($targetToken, 0, 10) . "...");

                // Load Service Account
                $serviceAccountPath = __DIR__ . '/../service-account.json';
                if (file_exists($serviceAccountPath)) {
                    $credentials = json_decode(file_get_contents($serviceAccountPath), true);
                    $clientEmail = $credentials['client_email'];
                    $privateKey = $credentials['private_key'];
                    $projectId = $credentials['project_id'];

                    // Generate JWT
                    if (!function_exists('base64UrlEncodeAssignment')) {
                        function base64UrlEncodeAssignment($data) {
                            return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
                        }
                    }

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

                    $base64Header = base64UrlEncodeAssignment($header);
                    $base64Payload = base64UrlEncodeAssignment($payload);
                    $signatureInput = $base64Header . "." . $base64Payload;

                    $signature = '';
                    if (openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
                        $jwt = $signatureInput . "." . base64UrlEncodeAssignment($signature);

                        // Exchange JWT for Access Token
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                            'assertion' => $jwt
                        ]));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $response = curl_exec($ch);
                        if (curl_errno($ch)) {
                            logAssignment("Curl JWT Error: " . curl_error($ch));
                        }
                        curl_close($ch);

                        $tokenData = json_decode($response, true);
                        if (isset($tokenData['access_token'])) {
                            $accessToken = $tokenData['access_token'];
                            logAssignment("Google Access Token acquired");

                            // Send FCM V1 Notification
                            $fcmUrl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

                            $payloadData = [
                                'message' => [
                                    'token' => $targetToken,
                                    'notification' => [
                                        'title' => $notifTitle,
                                        'body' => $notifBody
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
                                        'screen' => 'assignment',
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                        'task_id' => (string) $assignment_id,
                                        'notification_id' => (string) $db_notification_id,
                                        'type' => 'assignment'
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
                                $fcmSent = true;
                                logAssignment("FCM sent successfully to user $assigned_to");
                            } else {
                                logAssignment("FCM failed (HTTP $httpCode): $fcmResult");
                            }
                        } else {
                            logAssignment("Failed to get Access Token. Response: " . $response);
                        }
                    } else {
                        logAssignment("OpenSSL Sign Failed");
                    }
                } else {
                    logAssignment("Service Account file not found: $serviceAccountPath");
                }
            } else {
                logAssignment("User $assigned_to has no FCM token registered.");
            }
        } catch (Exception $e) {
            logAssignment("FCM Exception: " . $e->getMessage());
            // Silent fail - notification error should not stop success response
        }

        // ========== 5. SUCCESS RESPONSE ==========
        echo json_encode([
            "status" => "success",
            "message" => "Tugas baru berhasil didelegasikan!",
            "data" => [
                "id" => (int)$assignment_id,
                "notification_sent" => $fcmSent
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan tugas ke database."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal Server Error: " . $e->getMessage()]);
}
