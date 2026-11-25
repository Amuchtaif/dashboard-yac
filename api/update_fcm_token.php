<?php
// api/update_fcm_token.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../config/database.php';

// --- FITUR LOGGING (CCTV) ---
// Ini akan membuat file 'fcm_debug.log' di folder api
function catatLog($pesan)
{
    file_put_contents('fcm_debug.log', date('[Y-m-d H:i:s] ') . $pesan . PHP_EOL, FILE_APPEND);
}

// Tangkap semua input (baik form-data maupun raw json)
$user_id = $_POST['user_id'] ?? '';
$token = $_POST['fcm_token'] ?? '';
$app_version = trim((string)($_POST['app_version'] ?? ''));
$build_number = (int)($_POST['build_number'] ?? 0);

// Cek juga kalau dikirim via Raw JSON (Flutter sering kirim begini)
if (empty($user_id) || empty($token)) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!empty($data)) {
        $user_id = $data['user_id'] ?? $user_id;
        $token = $data['fcm_token'] ?? $token;
        $app_version = !empty($data['app_version']) ? trim((string)$data['app_version']) : $app_version;
        $build_number = !empty($data['build_number']) ? (int)$data['build_number'] : $build_number;
    }
}

// 1. CATAT APA YANG DITERIMA
catatLog("Request Masuk! User ID: '$user_id' | Token: " . substr($token, 0, 15) . "...");

if (empty($user_id) || empty($token)) {
    catatLog("GAGAL: Data kosong/tidak lengkap.");
    echo json_encode(["success" => false, "message" => "Data incomplete"]);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // 2. CEK APAKAH USER ADA?
    $check = $conn->prepare("SELECT id FROM employees WHERE id = :id");
    $check->bindParam(":id", $user_id);
    $check->execute();

    if ($check->rowCount() == 0) {
        catatLog("GAGAL: User ID $user_id tidak ditemukan di database.");
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit();
    }

    // 3. UPDATE TOKEN & METRIK VERSI/AKTIVITAS MOBILE
    $updateCols = ["fcm_token = :token", "last_mobile_activity = NOW()"];
    $params = [
        ':token' => $token,
        ':id' => $user_id
    ];

    if (!empty($app_version)) {
        $updateCols[] = "app_version = :ver";
        $params[':ver'] = $app_version;
    }
    if ($build_number > 0) {
        $updateCols[] = "app_build_number = :build";
        $params[':build'] = $build_number;
    }

    $query = "UPDATE employees SET " . implode(', ', $updateCols) . " WHERE id = :id";
    $stmt = $conn->prepare($query);

    if ($stmt->execute($params)) {
        catatLog("SUKSES: Token berhasil diupdate untuk User ID $user_id.");
        echo json_encode(["success" => true, "message" => "Token updated"]);
    } else {
        catatLog("ERROR SQL: Gagal eksekusi query.");
        echo json_encode(["success" => false, "message" => "Update failed"]);
    }
} catch (Exception $e) {
    catatLog("ERROR SYSTEM: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>