<?php
// api/create_meeting.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/db_mysqli.php';

// Cek Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

// 1. Ambil Data Input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// Support both JSON body and Form Data, prioritize JSON body if available
$title = $input['title'] ?? $_POST['title'] ?? '';
$description = $input['description'] ?? $_POST['description'] ?? '';
$meeting_date = $input['date'] ?? $_POST['date'] ?? '';
$start_time = $input['start_time'] ?? $_POST['start_time'] ?? '';
$end_time = $input['end_time'] ?? $_POST['end_time'] ?? '';
$type = $input['type'] ?? $_POST['type'] ?? ''; // online, offline
$location = $input['location'] ?? $_POST['location'] ?? '';
$link = $input['link'] ?? $_POST['link'] ?? '';
$created_by = $input['created_by'] ?? $_POST['created_by'] ?? '';
$division_id = $input['division_id'] ?? $_POST['division_id'] ?? '';

// Accept both 'participants' (from Flutter) and 'participant_ids' (legacy)
$participant_ids = $input['participants'] ?? $input['participant_ids'] ?? $_POST['participant_ids'] ?? [];

// Jika participant_ids dikirim sebagai string (misal dari form-data: "[1,2,3]" atau "1,2,3")
if (!is_array($participant_ids)) {
    // Coba decode json
    $decoded = json_decode($participant_ids, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $participant_ids = $decoded;
    } else {
        // Coba explode jika koma separated
        $participant_ids = explode(',', $participant_ids);
    }
}

// 2. Validasi
if (empty($title) || empty($meeting_date) || empty($start_time) || empty($end_time) || empty($type) || empty($created_by) || empty($division_id)) {
    echo json_encode(["success" => false, "message" => "Kolom wajib diisi: title, date, start_time, end_time, type, created_by, division_id."]);
    exit();
}

// Validasi Type Enum
if (!in_array($type, ['online', 'offline'])) {
    echo json_encode(["success" => false, "message" => "Type harus 'online' atau 'offline'."]);
    exit();
}

// 2b. Validasi Hak Akses (Can Create Meeting)
$sqlPerm = "SELECT p.can_create_meeting FROM employees e 
            JOIN positions p ON e.position_id = p.id 
            WHERE e.id = ?";
$stmtPerm = $mysqli->prepare($sqlPerm);
$stmtPerm->bind_param("i", $created_by);
$stmtPerm->execute();
$resPerm = $stmtPerm->get_result();
$permData = $resPerm->fetch_assoc();

if (!$permData || $permData['can_create_meeting'] != 1) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki hak akses untuk membuat rapat."]);
    exit();
}

// 3. Generate Token Unik
$qr_token = uniqid('MEET-');

// 4. Proses Insert (Transaction)
$mysqli->begin_transaction();

try {
    // A. Insert ke tabel meetings
    $sqlMeeting = "INSERT INTO meetings (title, description, meeting_date, start_time, end_time, type, location, created_by, division_id, qr_token) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sqlMeeting);
    
    // Use link if online, location if offline
    $locationOrLink = ($type === 'online') ? $link : $location;
    
    $stmt->bind_param(
        "sssssssiis",
        $title,
        $description,
        $meeting_date,
        $start_time,
        $end_time,
        $type,
        $locationOrLink,
        $created_by,
        $division_id,
        $qr_token
    );

    if (!$stmt->execute()) {
        throw new Exception("Gagal membuat meeting: " . $stmt->error);
    }

    $meeting_id = $mysqli->insert_id;

    // B. Insert ke tabel meeting_participants
    $participantCount = 0;
    if (!empty($participant_ids)) {
        $sqlParticipant = "INSERT INTO meeting_participants (meeting_id, employee_id, status) VALUES (?, ?, 'invited')";
        $stmtPart = $mysqli->prepare($sqlParticipant);

        foreach ($participant_ids as $p_id) {
            $p_id_clean = intval(trim($p_id));
            if ($p_id_clean > 0) {
                $stmtPart->bind_param("ii", $meeting_id, $p_id_clean);
                if (!$stmtPart->execute()) {
                    throw new Exception("Gagal menambahkan peserta ID: $p_id_clean - " . $stmtPart->error);
                }
                $participantCount++;
            }
        }
    }

    // Commit Transaction
    $mysqli->commit();

    // ========== NOTIFIKASI FCM KE SEMUA PESERTA ==========
    $notificationsSent = 0;
    
    if (!empty($participant_ids)) {
        // Log function
        function logMeetingFCM($msg) {
            file_put_contents('fcm_debug.log', date('Y-m-d H:i:s') . " [MEETING] - " . $msg . "\n", FILE_APPEND);
        }
        
        logMeetingFCM("Starting FCM notifications for meeting ID: $meeting_id");
        
        try {
            // Get creator name
            $stmtCreator = $mysqli->prepare("SELECT full_name FROM employees WHERE id = ? LIMIT 1");
            $stmtCreator->bind_param("i", $created_by);
            $stmtCreator->execute();
            $creatorResult = $stmtCreator->get_result();
            $creatorData = $creatorResult->fetch_assoc();
            $creatorName = $creatorData['full_name'] ?? 'Seseorang';
            
            // Format date for notification
            $meetingDateFormatted = date('d M Y', strtotime($meeting_date));
            $startTimeFormatted = substr($start_time, 0, 5); // HH:mm
            
            // Get FCM tokens of all participants
            $placeholders = implode(',', array_fill(0, count($participant_ids), '?'));
            $types = str_repeat('i', count($participant_ids));
            
            $sqlTokens = "SELECT id, fcm_token FROM employees WHERE id IN ($placeholders) AND fcm_token IS NOT NULL AND fcm_token != ''";
            $stmtTokens = $mysqli->prepare($sqlTokens);
            $stmtTokens->bind_param($types, ...$participant_ids);
            $stmtTokens->execute();
            $tokensResult = $stmtTokens->get_result();
            
            // Load Service Account for FCM
            $serviceAccountPath = 'service-account.json';
            if (file_exists($serviceAccountPath)) {
                $credentials = json_decode(file_get_contents($serviceAccountPath), true);
                $clientEmail = $credentials['client_email'];
                $privateKey = $credentials['private_key'];
                $projectId = $credentials['project_id'];
                
                // Generate Access Token (JWT)
                if (!function_exists('base64UrlEncodeMeeting')) {
                    function base64UrlEncodeMeeting($data) {
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
                
                $base64Header = base64UrlEncodeMeeting($header);
                $base64Payload = base64UrlEncodeMeeting($payload);
                $signatureInput = $base64Header . "." . $base64Payload;
                
                $signature = '';
                if (openssl_sign($signatureInput, $signature, $privateKey, 'SHA256')) {
                    $jwt = $signatureInput . "." . base64UrlEncodeMeeting($signature);
                    
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
                    curl_close($ch);
                    
                    $tokenData = json_decode($response, true);
                    
                    if (isset($tokenData['access_token'])) {
                        $accessToken = $tokenData['access_token'];
                        $fcmUrl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
                        
                        // Send to each participant
                        while ($row = $tokensResult->fetch_assoc()) {
                            $targetToken = $row['fcm_token'];
                            $participantId = $row['id'];
                            
                            $payloadData = [
                                'message' => [
                                    'token' => $targetToken,
                                    'notification' => [
                                        'title' => 'Undangan Rapat Baru',
                                        'body' => "$creatorName mengundang Anda ke rapat \"$title\" pada $meetingDateFormatted pukul $startTimeFormatted"
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
                                        'screen' => 'meeting',
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                        'meeting_id' => (string) $meeting_id,
                                        'type' => 'meeting_invitation'
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
                                $notificationsSent++;
                                logMeetingFCM("FCM sent to participant ID $participantId");
                            } else {
                                logMeetingFCM("FCM failed for participant ID $participantId: $fcmResult");
                            }
                        }
                    } else {
                        logMeetingFCM("Failed to get access token: $response");
                    }
                } else {
                    logMeetingFCM("OpenSSL sign failed");
                }
            } else {
                logMeetingFCM("Service account file not found");
            }
        } catch (Exception $e) {
            logMeetingFCM("FCM Exception: " . $e->getMessage());
            // Silent fail - notification error should not stop success response
        }
        
        logMeetingFCM("FCM completed. Sent: $notificationsSent notifications");
    }
    // ========== END NOTIFIKASI FCM ==========

    echo json_encode([
        "success" => true,
        "message" => "Meeting berhasil dibuat.",
        "meeting_id" => $meeting_id,
        "qr_token" => $qr_token,
        "participants_added" => $participantCount,
        "notifications_sent" => $notificationsSent
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan: " . $e->getMessage()
    ]);
}

$mysqli->close();
?>
