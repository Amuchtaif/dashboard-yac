<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$status = $data['status'] ?? null;
$user_id = $data['user_id'] ?? null; // ID of the substitute who approves/denies

if (!$id || !$status || !$user_id) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

if (!in_array($status, ['Disetujui', 'Ditolak'])) {
    echo json_encode(["success" => false, "message" => "Invalid status"]);
    exit;
}

try {
    $db->beginTransaction();

    // 1. Get request details before update
    $checkQuery = "SELECT requester_id, substitute_id, exchange_date FROM shift_exchanges WHERE id = ?";
    $stmtC = $db->prepare($checkQuery);
    $stmtC->execute([$id]);
    $row = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $db->rollBack();
        echo json_encode(["success" => false, "message" => "Request not found"]);
        exit;
    }

    if ($row['substitute_id'] != $user_id) {
        $db->rollBack();
        echo json_encode(["success" => false, "message" => "Unauthorized to handle this request"]);
        exit;
    }

    $requester_id = $row['requester_id'];
    $exchange_date = $row['exchange_date'];

    // 2. Update status
    $query = "UPDATE shift_exchanges SET status = ?, approved_by = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$status, $user_id, $id])) {
        
        // --- FCM NOTIFICATION TO REQUESTER ---
        try {
            // Get substitute (approver) full name
            $stmt_sub = $db->prepare("SELECT full_name FROM employees WHERE id = ?");
            $stmt_sub->execute([$user_id]);
            $sub_data = $stmt_sub->fetch(PDO::FETCH_ASSOC);
            $substitute_name = $sub_data['full_name'] ?? 'Rekan Kerja';

            // Get requester FCM token
            $stmt_req = $db->prepare("SELECT fcm_token FROM employees WHERE id = ?");
            $stmt_req->execute([$requester_id]);
            $req_data = $stmt_req->fetch(PDO::FETCH_ASSOC);
            $targetToken = $req_data['fcm_token'] ?? null;

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
                        $statusLower = strtolower($status);
                        
                        $payloadData = [
                            'message' => [
                                'token' => $targetToken,
                                'notification' => [
                                    'title' => "Tukar Shift $status",
                                    'body' => "$substitute_name telah $statusLower permintaan tukar shift Anda untuk tanggal $formattedDate"
                                ],
                                'android' => [
                                    'priority' => 'HIGH',
                                    'notification' => [
                                        'channel_id' => 'high_importance_channel',
                                        'sound' => 'default'
                                    ]
                                ],
                                'data' => [
                                    'screen' => 'shift_exchange',
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                    'exchange_id' => (string)$id,
                                    'type' => 'swap_status_update'
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
                        curl_exec($ch);
                    }
                }
            }
        } catch (Exception $e_notif) {
            // Silently fail or log notification error
            file_put_contents('fcm_update_debug.log', date('Y-m-d H:i:s') . " - EXCEPTION: " . $e_notif->getMessage() . "\n", FILE_APPEND);
        }
        // --- END FCM ---

        $db->commit();
        echo json_encode(["success" => true, "message" => "Permintaan telah " . ($status == 'Disetujui' ? 'disetujui' : 'ditolak')]);
    } else {
        $db->rollBack();
        echo json_encode(["success" => false, "message" => "Failed to update status"]);
    }

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
