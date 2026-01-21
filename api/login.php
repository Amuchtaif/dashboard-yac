<?php
// 1. Matikan error HTML agar JSON bersih
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Include file class Database Anda
// Asumsi: File config di atas Anda simpan dengan nama 'db_connect.php' di folder luar 'api'
include_once '../config/database.php';

// 3. INI BAGIAN KUNCI PERBAIKANNYA (Instansiasi Class)
try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Koneksi Gagal: " . $e->getMessage()]);
    exit();
}

// 4. Ambil data JSON dari Flutter
$json = file_get_contents("php://input");
$data = json_decode($json);

if (!isset($data->email) || !isset($data->password)) {
    echo json_encode(["success" => false, "message" => "Email dan Password wajib diisi"]);
    exit();
}

$email = $data->email;
$password = $data->password;

try {
    // Query tetap sama (menggunakan $conn yang sudah didapat dari class)
    $query = "SELECT 
                e.id, 
                e.full_name, 
                e.email, 
                e.phone_number,
                e.password, 
                u.name AS unit_name, 
                d.name AS division_name,
                p.level AS position_level,
                p.name AS position_name
              FROM employees e 
              LEFT JOIN positions p ON e.position_id = p.id
              LEFT JOIN units u ON e.unit_id = u.id 
              LEFT JOIN divisions d ON e.division_id = d.id 
              WHERE e.email = :email";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);

        echo json_encode([
            "success" => true,
            "message" => "Login Berhasil",
            "data" => $user
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Email atau Password salah"
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>