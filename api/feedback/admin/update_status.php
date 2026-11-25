<?php
// api/feedback/admin/update_status.php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metode ' . $_SERVER['REQUEST_METHOD'] . ' tidak diizinkan. Gunakan POST.'
    ]);
    exit();
}

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/fcm_helper.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Read input (support JSON and POST form data)
    $raw_input = file_get_contents("php://input");
    $json_data = json_decode($raw_input, true) ?: [];

    $feedback_id = isset($_POST['feedback_id']) ? (int)$_POST['feedback_id'] : (int)($json_data['feedback_id'] ?? 0);
    $status = trim($_POST['status'] ?? ($json_data['status'] ?? ''));
    $admin_notes = trim($_POST['admin_notes'] ?? ($json_data['admin_notes'] ?? ''));
    
    // Resolve admin_id
    $admin_id = null;
    if (isset($_POST['admin_id']) && (int)$_POST['admin_id'] > 0) {
        $admin_id = (int)$_POST['admin_id'];
    } elseif (isset($json_data['admin_id']) && (int)$json_data['admin_id'] > 0) {
        $admin_id = (int)$json_data['admin_id'];
    } elseif (!empty($_SESSION['user_id'])) {
        $admin_id = (int)$_SESSION['user_id'];
    }

    if ($feedback_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter feedback_id wajib diisi']);
        exit();
    }

    $valid_statuses = ['pending', 'reviewed', 'in_progress', 'completed', 'rejected'];
    if (!in_array($status, $valid_statuses, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Status tidak valid. Pilihan: ' . implode(', ', $valid_statuses)
        ]);
        exit();
    }

    // Check feedback exists and fetch current values
    $checkStmt = $db->prepare("
        SELECT uf.id, uf.user_id, uf.title, uf.status, uf.admin_notes, e.fcm_token, e.full_name
        FROM user_feedbacks uf
        LEFT JOIN employees e ON uf.user_id = e.id
        WHERE uf.id = :id
        LIMIT 1
    ");
    $checkStmt->execute([':id' => $feedback_id]);
    $current = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data usulan tidak ditemukan']);
        exit();
    }

    // Update feedback record
    $updateStmt = $db->prepare("
        UPDATE user_feedbacks 
        SET 
            status = :status,
            admin_notes = :admin_notes,
            admin_id = :admin_id,
            updated_at = NOW()
        WHERE id = :id
    ");

    $updateStmt->execute([
        ':status' => $status,
        ':admin_notes' => $admin_notes ?: null,
        ':admin_id' => $admin_id,
        ':id' => $feedback_id
    ]);

    // Send FCM Push Notification if status changed or notes provided
    $notificationSent = false;
    $userToken = $current['fcm_token'] ?? '';
    $userId = (int)$current['user_id'];
    $feedbackTitle = $current['title'] ?? 'Usulan Fitur';

    $statusLabels = [
        'pending' => 'Menunggu',
        'reviewed' => 'Ditinjau',
        'in_progress' => 'Dalam Antrean Rilis',
        'completed' => 'Selesai',
        'rejected' => 'Ditunda'
    ];
    $statusLabel = $statusLabels[$status] ?? $status;

    if (!empty($userToken)) {
        try {
            $fcm = new FcmHelper();
            $notifTitle = "Tanggapan Usulan Fitur";
            
            if (!empty($admin_notes)) {
                $notifBody = "Tim IT telah menanggapi usulan Anda: \"" . $feedbackTitle . "\" (Status: " . $statusLabel . ")";
            } else {
                $notifBody = "Status usulan Anda: \"" . $feedbackTitle . "\" diperbarui menjadi " . $statusLabel . ".";
            }

            $notifData = [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'type' => 'feedback',
                'feedback_id' => (string)$feedback_id,
                'status' => (string)$status,
                'screen' => 'feedback_detail',
                'title' => $notifTitle,
                'body' => $notifBody
            ];

            $fcmResult = $fcm->sendNotification($userToken, $notifTitle, $notifBody, $notifData);
            if ($fcmResult !== false) {
                $notificationSent = true;
            }

            // Save to notifications inbox table if exists
            try {
                $stmtInbox = $db->prepare("
                    INSERT INTO notifications (user_id, title, body, type, is_read, created_at)
                    VALUES (:user_id, :title, :body, 'feedback', 0, NOW())
                ");
                $stmtInbox->execute([
                    ':user_id' => $userId,
                    ':title' => $notifTitle,
                    ':body' => $notifBody
                ]);
            } catch (\Throwable $inboxErr) {
                // Ignore inbox error if table structure differs
                error_log("Feedback inbox notification insert error: " . $inboxErr->getMessage());
            }

        } catch (\Throwable $fcmErr) {
            error_log("FCM Notification error for feedback #{$feedback_id}: " . $fcmErr->getMessage());
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Status dan catatan balasan berhasil diperbarui',
        'notification_sent' => $notificationSent
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan di server: ' . $e->getMessage()
    ]);
}
