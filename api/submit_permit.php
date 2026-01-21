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

// Validasi
if (empty($user_id) || empty($permit_type) || empty($start_date) || empty($end_date) || empty($reason)) {
    echo json_encode(["success" => false, "message" => "Semua kolom wajib diisi."]);
    exit();
}

// 2. Handle File Upload
$attachmentName = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
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
            echo json_encode(["success" => false, "message" => "Gagal upload file."]);
            exit();
        }
    } else {
        echo json_encode(["success" => false, "message" => "Format file harus JPG, PNG, atau PDF."]);
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

        // --- ALUR PENCARIAN ATASAN BERTINGKAT ---

        // SKENARIO 1: STAFF / GURU (Level >= 4)
        if ($level >= 4) {
            // Priority A: Cari KEPALA UNIT (Level 3)
            $approver_id = findBoss($conn, 3, 'unit_id', $unit_id);

            // Priority B (Fallback): Jika Ka. Unit tidak ada, cari KEPALA BIDANG (Level 2)
            if (!$approver_id) {
                $approver_id = findBoss($conn, 2, 'division_id', $division_id);
            }
        }

        // SKENARIO 2: KEPALA UNIT (Level 3)
        elseif ($level == 3) {
            // Cari KEPALA BIDANG (Level 2)
            $approver_id = findBoss($conn, 2, 'division_id', $division_id);
        }

        // SKENARIO 3: SAFETY NET (JARING PENGAMAN)
        // Jika sampai sini approver_id masih NULL (misal Ka. Bidang juga kosong), 
        // ATAU jika Pelapor adalah Ka. Bidang (Level 2)
        // Maka lemparkan ke MUDIR (Level 1)
        if (!$approver_id && $level != 1) {
            $stmtMudir = $conn->prepare("SELECT e.id FROM employees e JOIN positions p ON e.position_id = p.id WHERE p.level = 1 LIMIT 1");
            $stmtMudir->execute();
            $mudir = $stmtMudir->fetch(PDO::FETCH_ASSOC);
            if ($mudir) {
                $approver_id = $mudir['id'];
            }
        }
    }

    // 4. INSERT DATA
    $sql = "INSERT INTO permits (employee_id, permit_type, start_date, end_date, reason, attachment, status, approver_id) 
            VALUES (:uid, :type, :sdate, :edate, :reason, :attach, 'Pending', :app_id)";

    $stmtInsert = $conn->prepare($sql);
    $stmtInsert->bindParam(':uid', $user_id);
    $stmtInsert->bindParam(':type', $permit_type);
    $stmtInsert->bindParam(':sdate', $start_date);
    $stmtInsert->bindParam(':edate', $end_date);
    $stmtInsert->bindParam(':reason', $reason);
    $stmtInsert->bindParam(':attach', $attachmentName);
    $stmtInsert->bindParam(':app_id', $approver_id);

    if ($stmtInsert->execute()) {
        // --- NOTIFICATION LOGIC (FCM V1) ---
        if ($approver_id) {
            try {
                // 1. Get Approver Token
                $stmtToken = $conn->prepare("SELECT fcm_token FROM employees WHERE id = :aid LIMIT 1");
                $stmtToken->execute([':aid' => $approver_id]);
                $tokenData = $stmtToken->fetch(PDO::FETCH_ASSOC);

                if ($tokenData && !empty($tokenData['fcm_token'])) {
                    $approverToken = $tokenData['fcm_token'];

                    // 2. Load Service Account to get Project ID
                    $keyFilePath = '../config/service-account.json';
                    if (file_exists($keyFilePath)) {
                        $keyFile = json_decode(file_get_contents($keyFilePath), true);
                        $projectId = $keyFile['project_id'];

                        // 3. Get Access Token
                        $tokenGen = new GoogleAccessToken($keyFilePath);
                        $accessToken = $tokenGen->getToken();

                        if ($accessToken) {
                            // 4. Construct Payload (FCM V1)
                            $title = "Izin Masuk Baru";
                            $body = "Ada pengajuan izin baru dari ID: $user_id. Tipe: $permit_type";

                            $payload = [
                                'message' => [
                                    'token' => $approverToken,
                                    'notification' => [
                                        'title' => $title,
                                        'body' => $body
                                    ],
                                    'data' => [
                                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                        'screen' => 'approval_list',
                                        'permit_id' => (string) $conn->lastInsertId()
                                    ]
                                ]
                            ];

                            // 5. Send Request
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/$projectId/messages:send");
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Authorization: Bearer ' . $accessToken,
                                'Content-Type: application/json'
                            ]);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);

                            // Optional: Log response if needed for debugging
                            // file_put_contents('fcm_log.txt', $response . PHP_EOL, FILE_APPEND);
                        }
                    }
                }
            } catch (Exception $e) {
                // Silent fail for notification to not block permit submission
                // file_put_contents('fcm_error.txt', $e->getMessage() . PHP_EOL, FILE_APPEND);
            }
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