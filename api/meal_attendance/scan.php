<?php
// api/meal_attendance/scan.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $json = file_get_contents("php://input");
    $data = json_decode($json);

    if (!isset($data->student_id) || !isset($data->meal_type)) {
        echo json_encode(["success" => false, "message" => "Parameter tidak lengkap."]);
        exit();
    }

    $id = $data->student_id;
    $type = $data->meal_type; // Pagi, Siang, Malam
    $today = date('Y-m-d');
    $now = date('H:i:s');

    // 1. Validasi Siswa Exist
    $stmtS = $conn->prepare("SELECT id, nama_siswa FROM students WHERE id = ?");
    $stmtS->execute([$id]);
    $student = $stmtS->fetch();

    if (!$student) {
        echo json_encode(["success" => false, "message" => "Siswa tidak ditemukan."]);
        exit();
    }

    // 2. Cek Double Scan di waktu yang sama (Double Dipping)
    $stmtCheck = $conn->prepare("SELECT id FROM meal_attendances WHERE student_id = ? AND meal_type = ? AND date = ?");
    $stmtCheck->execute([$id, $type, $today]);
    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(["success" => false, "message" => "{$student['nama_siswa']} sudah mengambil jatah makan {$type} hari ini."]);
        exit();
    }

    // 3. Simpan
    $stmtInsert = $conn->prepare("INSERT INTO meal_attendances (student_id, meal_type, date, check_time) VALUES (?, ?, ?, ?)");
    if ($stmtInsert->execute([$id, $type, $today, $now])) {
        echo json_encode([
            "success" => true, 
            "message" => "Absensi makan {$type} berhasil untuk {$student['nama_siswa']}.",
            "data" => [
                "student_name" => $student['nama_siswa'],
                "time" => substr($now, 0, 5)
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal menyimpan data."]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
