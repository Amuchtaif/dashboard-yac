<?php
/**
 * Change Password API
 * Endpoint: POST /api/change_password.php
 */

error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

// Include database class
include_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Koneksi Gagal: " . $e->getMessage()]);
    exit();
}

// Get data
$json = file_get_contents("php://input");
$data = json_decode($json);

if (!isset($data->user_id) || !isset($data->old_password) || !isset($data->new_password)) {
    echo json_encode(["success" => false, "message" => "Parameter tidak lengkap"]);
    exit();
}

$user_id = $data->user_id;
$old_password = $data->old_password;
$new_password = $data->new_password;

try {
    // 1. Fetch user from employees table (where passwords are kept match login.php)
    $stmt = $conn->prepare("SELECT password FROM employees WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
        exit();
    }

    // 2. Verify old password
    if (!password_verify($old_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Kata sandi lama salah.']);
        exit();
    }

    // 3. Update to new password
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE employees SET password = :new_password WHERE id = :id");
    $updateStmt->bindParam(':new_password', $new_hashed_password);
    $updateStmt->bindParam(':id', $user_id);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Kata sandi berhasil diubah!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui kata sandi.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
