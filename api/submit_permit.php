<?php
// api/submit_permit.php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';
include_once 'AccessToken.php';

$database = new Database();
$conn = $database->getConnection();

// Cek Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

// 1. Ambil Data Input
$user_id = $_POST['user_id'] ?? '';
$permit_type = $_POST['permit_type'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$reason = $_POST['reason'] ?? '';
$is_hourly = $_POST['is_hourly'] ?? 0;
$start_time = $_POST['start_time'] ?? null;
$end_time = $_POST['end_time'] ?? null;

// Validasi
if (empty($user_id) || empty($permit_type) || empty($start_date) || empty($end_date) || empty($reason)) {
    echo json_encode(["success" => false, "message" => "Semua kolom wajib diisi."]);
    exit();
}

// 2. Handle File Upload
$attachmentName = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['name'] !== '') {
    if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $fileName = $_FILES['attachment']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = '../uploads/permits/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $newFileName = time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $attachmentName = $newFileName;
            } else {
                echo json_encode(["success" => false, "message" => "Gagal memindahkan file ke folder uploads."]);
                exit();
            }
        } else {
            echo json_encode(["success" => false, "message" => "Format file harus JPG, PNG, atau PDF."]);
            exit();
        }
    } else {
        $errorMsg = "Gagal upload file (Error: " . $_FILES['attachment']['error'] . ")";
        if ($_FILES['attachment']['error'] === UPLOAD_ERR_INI_SIZE) $errorMsg = "File terlalu besar (Server Limit).";
        echo json_encode(["success" => false, "message" => $errorMsg]);
        exit();
    }
}

// 3. LOGIC HIERARKI APPROVER (REVISI ROBUST / FALLBACK SYSTEM)
$approver_id = null;

try {
    // Ambil Data Karyawan Pelapor
    $stmt = $conn->prepare("
        SELECT e.id, e.unit_id, e.division_id, p.level 
        FROM employees e 
        LEFT JOIN positions p ON e.position_id = p.id 
        WHERE e.id = :id
    ");
    $stmt->execute([':id' => $user_id]);
    $empData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empData) {
        $level = (int) $empData['level'];
        $unit_id = $empData['unit_id'];
        $division_id = $empData['division_id'];

        // FUNGSI BANTUAN: Cari Atasan
        // Mencari karyawan dengan Level tertentu di Unit/Divisi tertentu
        function findBoss($connection, $targetLevel, $colName, $colValue)
        {
            if (empty($colValue))
                return false;

            $sql = "SELECT e.id FROM employees e 
                    JOIN positions p ON e.position_id = p.id 
                    WHERE e.$colName = :val AND p.level = :lvl 
                    AND e.status = 'active' 
                    LIMIT 1";
            $stmt = $connection->prepare($sql);
            $stmt->execute([':val' => $colValue, ':lvl' => $targetLevel]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ? $res['id'] : false;
        }

        // --- ALUR PENCARIAN ATASAN BERTINGKAT (WATERFALL) ---
        // 1. STAFF / GURU (Level 4 atau 5)
        if ($level >= 4) {
            // A. Cari Kepala Unit (Level 3) di Unit yang sama
            if (!empty($unit_id)) {
                $approver_id = findBoss($conn, 3, 'unit_id', $unit_id);
            }

            // B. Jika tidak ada Ka Unit, cari Kabid (Level 2) di Divisi yang sama
            if (!$approver_id && !empty($division_id)) {
                $approver_id = findBoss($conn, 2, 'division_id', $division_id);
            }
        }
        // 2. KEPALA UNIT (Level 3)
        elseif ($level == 3) {
            // Cari Kabid (Level 2) di Divisi yang sama
            if (!empty($division_id)) {
                $approver_id = findBoss($conn, 2, 'division_id', $division_id);
            }
        }
        // 3. KEPALA BIDANG (Level 2)
        elseif ($level == 2) {
            // Mudir (Level 1) HANYA menerima dari Level 2 (Kabid)
            $stmtMudir = $conn->prepare("
                SELECT e.id FROM employees e 
                JOIN positions p ON e.position_id = p.id 
                WHERE p.level = 1 AND e.status = 'active' 
                LIMIT 1
            ");
            $stmtMudir->execute();
            $mudir = $stmtMudir->fetch(PDO::FETCH_ASSOC);
            if ($mudir) {
                $approver_id = $mudir['id'];
            }
        }

        // --- PREVENT SELF-APPROVAL ---
        if ($approver_id == $user_id) {
            $approver_id = null; // Don't let user approve themselves
        }
    }

    // 4. INSERT DATA
    $sql = "INSERT INTO permits (employee_id, permit_type, start_date, end_date, reason, attachment, status, approver_id, is_hourly, start_time, end_time) 
            VALUES (:uid, :type, :sdate, :edate, :reason, :attach, 'Pending', :app_id, :is_hourly, :start_time, :end_time)";

    $stmtInsert = $conn->prepare($sql);
    $stmtInsert->bindParam(':uid', $user_id);
    $stmtInsert->bindParam(':type', $permit_type);
    $stmtInsert->bindParam(':sdate', $start_date);
    $stmtInsert->bindParam(':edate', $end_date);
    $stmtInsert->bindParam(':reason', $reason);
    $stmtInsert->bindParam(':attach', $attachmentName);
    $stmtInsert->bindParam(':app_id', $approver_id);
    $stmtInsert->bindParam(':is_hourly', $is_hourly);
    $stmtInsert->bindParam(':start_time', $start_time);
    $stmtInsert->bindParam(':end_time', $end_time);

    if ($stmtInsert->execute()) {
        // --- NOTIFICATION LOGIC (FCM V1 - Using GoogleAccessToken) ---
        function logFCM($msg)
        {
            file_put_contents('fcm_debug.log', date('Y-m-d H:i:s') . " [PERMIT] - " . $msg . "\n", FILE_APPEND);
        }

        logFCM("Starting Notification Process. Approver ID: " . ($approver_id ?? 'NULL'));

        if ($approver_id) {
            try {
                // 1. Get Approver Token
                $stmtToken = $conn->prepare("SELECT fcm_token FROM employees WHERE id = :aid LIMIT 1");
                $stmtToken->execute([':aid' => $approver_id]);
                $tokenRow = $stmtToken->fetch(PDO::FETCH_ASSOC);

                if ($tokenRow && !empty($tokenRow['fcm_token'])) {
                    $targetToken = $tokenRow['fcm_token'];
                    logFCM("Token found: " . substr($targetToken, 0, 10) . "...");

                    // 2. Get Access Token using shared helper
                    $serviceAccountPath = 'service-account.json';
                    if (file_exists($serviceAccountPath)) {
                        $googleToken = new GoogleAccessToken($serviceAccountPath);
                        $accessToken = $googleToken->getToken();

                        if ($accessToken) {
                            logFCM("Google Access Token Acquired");

                            $credentials = json_decode(file_get_contents($serviceAccountPath), true);
                            $projectId = $credentials['project_id'];

                            // 3. Kirim Notifikasi (FCM V1)
                            $fcmUrl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
                            $newPermitId = $conn->lastInsertId();

                            // Get Employee Name for clearer notification
                            $stmtName = $conn->prepare("SELECT full_name FROM employees WHERE id = :uid LIMIT 1");
                            $stmtName->execute([':uid' => $user_id]);
                            $empName = $stmtName->fetchColumn();
                            $senderName = $empName ? $empName : "ID: $user_id";

                            $payloadData = [
                                'message' => [
                                    'token' => $targetToken,
                                    'notification' => [
                                        'title' => 'Izin Baru: ' . $senderName,
                                        'body' => "Menunggu persetujuan Anda."
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
                                        'screen' => 'permit_approval',
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                        'permit_id' => (string) $newPermitId
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

                            if ($httpCode === 200) {
                                logFCM("FCM sent successfully to approver ID $approver_id");
                            } else {
                                logFCM("FCM failed (HTTP $httpCode): $fcmResult");
                            }
                        } else {
                            logFCM("Failed to get Access Token from GoogleAccessToken class");
                        }
                    } else {
                        logFCM("Service Account file not found: $serviceAccountPath");
                    }
                } else {
                    logFCM("Approver found but NO TOKEN or Token Empty.");
                }
            } catch (Exception $e) {
                logFCM("Exception: " . $e->getMessage());
                // Silent fail: Notification error should not stop the process
            }
        } else {
            logFCM("No Approver ID determined.");
        }
        // --- END NOTIFICATION ---

        echo json_encode([
            "success" => true,
            "message" => "Izin berhasil diajukan! Menunggu approval.",
            // Debug info (optional, bisa dihapus saat production)
            "debug_approver" => $approver_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal menyimpan database."]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>