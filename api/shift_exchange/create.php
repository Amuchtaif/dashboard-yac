<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);

$requester_id = $data['requester_id'] ?? null;
$substitute_id = $data['substitute_id'] ?? null;
$exchange_date = $data['exchange_date'] ?? null;
$reason = $data['reason'] ?? '';

if (!$requester_id || !$substitute_id || !$exchange_date) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

if ($requester_id == $substitute_id) {
    echo json_encode(["success" => false, "message" => "You cannot exchange shift with yourself"]);
    exit;
}

try {
    $db->beginTransaction();

    $query = "INSERT INTO shift_exchanges (requester_id, substitute_id, exchange_date, reason) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$requester_id, $substitute_id, $exchange_date, $reason])) {
        $exchange_id = $db->lastInsertId();
        
        // --- FCM NOTIFICATION ---
        $notificationsSent = false;
        try {
            // Get requester full name
            $stmt_req = $db->prepare("SELECT full_name FROM employees WHERE id = ?");
            $stmt_req->execute([$requester_id]);
            $req_data = $stmt_req->fetch(PDO::FETCH_ASSOC);
            $requester_name = $req_data['full_name'] ?? 'Rekan Kerja';

            // Get target (substitute) FCM token
            $stmt_sub = $db->prepare("SELECT fcm_token FROM employees WHERE id = ?");
            $stmt_sub->execute([$substitute_id]);
            $sub_data = $stmt_sub->fetch(PDO::FETCH_ASSOC);
            $targetToken = $sub_data['fcm_token'] ?? null;

            if ($targetToken) {
                require_once '../AccessToken.php';
                $serviceAccountPath = '../service-account.json';
                
                if (file_exists($serviceAccountPath)) {
                    $accessTokenObj = new GoogleAccessToken($serviceAccountPath);
                    $accessToken = $accessTokenObj->getToken();
                    
                    if ($accessToken) {
                        $credentials = json_decode(file_get_contents($serviceAccountPath), true);
                        $projectId = $credentials['project_id'];
                        $fcmUrl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
                        
                        $formattedDate = date('d M Y', strtotime($exchange_date));
                        
                        $payloadData = [
                            'message' => [
                                'token' => $targetToken,
                                'notification' => [
                                    'title' => 'Permintaan Tukar Shift',
                                    'body' => "$requester_name mengajak bertukar shift untuk tanggal $formattedDate"
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
                                    'screen' => 'shift_exchange',
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    'exchange_id' => (string)$exchange_id,
                                    'type' => 'incoming_shift_swap'
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
                        $fcmResultString = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        $notificationsSent = ($httpCode === 200);
                        
                        // Debug log
                        file_put_contents('fcm_swap_debug.log', date('Y-m-d H:i:s') . " - Swap ID $exchange_id - Code $httpCode - Response: $fcmResultString\n", FILE_APPEND);
                    }
                }
            }
        } catch (Exception $e_notif) {
             file_put_contents('fcm_swap_debug.log', date('Y-m-d H:i:s') . " - EXCEPTION: " . $e_notif->getMessage() . "\n", FILE_APPEND);
        }
        // --- END FCM ---

        $db->commit();
        echo json_encode([
            "success" => true, 
            "message" => "Permintaan tukar shift berhasil dikirim", 
            "notification_sent" => $notificationsSent
        ]);
    } else {
        $db->rollBack();
        echo json_encode(["success" => false, "message" => "Failed to create request"]);
    }
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
