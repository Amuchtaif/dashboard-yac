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

// Cek juga kalau dikirim via Raw JSON (Flutter sering kirim begini)
if (empty($user_id) || empty($token)) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!empty($data)) {
        $user_id = $data['user_id'] ?? $user_id;
        $token = $data['fcm_token'] ?? $token;
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

    // 3. UPDATE TOKEN
    $query = "UPDATE employees SET fcm_token = :token WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":token", $token);
    $stmt->bindParam(":id", $user_id);

    if ($stmt->execute()) {
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