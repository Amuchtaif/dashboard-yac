<?php
// api/submit_permit.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';
include_once 'AccessToken.php';

// Helper function to find approver
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

try {
    $database = new Database();
    $conn = $database->getConnection();

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
        ob_clean();
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
                    ob_clean();
                    echo json_encode(["success" => false, "message" => "Gagal memindahkan file ke folder uploads."]);
                    exit();
                }
            } else {
                ob_clean();
                echo json_encode(["success" => false, "message" => "Format file harus JPG, PNG, atau PDF."]);
                exit();
            }
        } else {
            $errorMsg = "Gagal upload file (Error: " . $_FILES['attachment']['error'] . ")";
            if ($_FILES['attachment']['error'] === UPLOAD_ERR_INI_SIZE) $errorMsg = "File terlalu besar (Server Limit).";
            ob_clean();
            echo json_encode(["success" => false, "message" => $errorMsg]);
            exit();
        }
    }

    // 3. LOGIC HIERARKI APPROVER
    $approver_id = null;

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

        // 1. STAFF / GURU (Level 4 atau 5)
        if ($level >= 4) {
            if (!empty($unit_id)) {
                $approver_id = findBoss($conn, 3, 'unit_id', $unit_id);
            }
            if (!$approver_id && !empty($division_id)) {
                $approver_id = findBoss($conn, 2, 'division_id', $division_id);
            }
        }
        // 2. KEPALA UNIT (Level 3)
        elseif ($level == 3) {
            if (!empty($division_id)) {
                $approver_id = findBoss($conn, 2, 'division_id', $division_id);
            }
        }
        // 3. KEPALA BIDANG (Level 2)
        elseif ($level == 2) {
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

        if ($approver_id == $user_id) {
            $approver_id = null;
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
        // --- NOTIFICATION LOGIC ---
        function logFCM($msg)
        {
            file_put_contents(__DIR__ . '/fcm_debug.log', date('Y-m-d H:i:s') . " [PERMIT] - " . $msg . "\n", FILE_APPEND);
        }

        if ($approver_id) {
            try {
                $stmtToken = $conn->prepare("SELECT fcm_token FROM employees WHERE id = :aid LIMIT 1");
                $stmtToken->execute([':aid' => $approver_id]);
                $tokenRow = $stmtToken->fetch(PDO::FETCH_ASSOC);

                if ($tokenRow && !empty($tokenRow['fcm_token'])) {
                    $targetToken = $tokenRow['fcm_token'];
                    $serviceAccountPath = __DIR__ . '/service-account.json';
                    
                    if (file_exists($serviceAccountPath)) {
                        $googleToken = new GoogleAccessToken($serviceAccountPath);
                        $accessToken = $googleToken->getToken();

                        if ($accessToken) {
                            $credentials = json_decode(file_get_contents($serviceAccountPath), true);
                            $projectId = $credentials['project_id'];
                            $fcmUrl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
                            $newPermitId = $conn->lastInsertId();

                            $stmtName = $conn->prepare("SELECT full_name FROM employees WHERE id = :uid LIMIT 1");
                            $stmtName->execute([':uid' => $user_id]);
                            $empName = $stmtName->fetchColumn();
                            $senderName = $empName ? $empName : "Pegawai";

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
                                            'sound' => 'default'
                                        ]
                                    ],
                                    'data' => [
                                        'screen' => 'permit_approval',
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
                            curl_exec($ch);
                            curl_close($ch);
                        }
                    }
                }
            } catch (Exception $e) {
                // Silent fail for notification
            }
        }

        ob_clean();
        echo json_encode([
            "success" => true,
            "message" => "Izin berhasil diajukan! Menunggu approval.",
            "debug_approver" => $approver_id
        ]);
    } else {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Gagal menyimpan pengajuan izin."]);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(["success" => false, "message" => "System Error: " . $e->getMessage()]);
}

?>