<?php
// 1. Setup Standar
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php'; // Sesuaikan path jika beda

$database = new Database();
$conn = $database->getConnection();

// 2. Validasi Input
if (!isset($_GET['user_id'])) {
    echo json_encode(["success" => false, "message" => "User ID diperlukan"]);
    exit();
}

$user_id = $_GET['user_id'];
$today = date('Y-m-d');
// $today = "2026-01-11";
$yesterday = date('Y-m-d', strtotime("-1 days")); // Hitung tanggal kemarin

try {
    // =================================================================================
    // BAGIAN 1: LAZY AUTO-CLOSE (Membersihkan Data Kemarin yang Lupa Pulang)
    // =================================================================================

    // Update status_out menjadi 'Lupa Absen Pulang' jika kemarin lupa absen
    $cleanupQuery = "UPDATE attendance 
                     SET time_out = '23:59:59', 
                         status_out = 'Lupa Absen Pulang' 
                     WHERE user_id = :uid 
                     AND date < :today 
                     AND time_out IS NULL";

    $stmtClean = $conn->prepare($cleanupQuery);
    $stmtClean->bindParam(':uid', $user_id);
    $stmtClean->bindParam(':today', $today);
    $stmtClean->execute();

    // =================================================================================
    // BAGIAN 2: CEK STATUS HARI INI (Untuk Tombol Absen)
    // =================================================================================

    $statusQuery = "SELECT time_in, time_out, status 
                    FROM attendance 
                    WHERE user_id = :uid AND date = :today";

    $stmtStatus = $conn->prepare($statusQuery);
    $stmtStatus->bindParam(':uid', $user_id);
    $stmtStatus->bindParam(':today', $today);
    $stmtStatus->execute();

    $rowToday = $stmtStatus->fetch(PDO::FETCH_ASSOC);

    // Tentukan Status String
    $currentStatus = "BELUM_ABSEN"; // Default
    $todayData = null;

    if ($rowToday) {
        if ($rowToday['time_in'] != null && $rowToday['time_out'] == null) {
            $currentStatus = "SUDAH_MASUK";
            $todayData = $rowToday;
        } elseif ($rowToday['time_out'] != null) {
            $currentStatus = "SELESAI";
            $todayData = $rowToday;
        }
    }

    // =================================================================================
    // BAGIAN 3: AMBIL HISTORY (FILTER HANYA 2 HARI TERAKHIR)
    // =================================================================================

    // Perhatikan penambahan: AND date >= :start_date
    $historyQuery = "SELECT type, time, date, status FROM (
                        -- Ambil Data MASUK
                        SELECT 
                            'Absen Masuk' as type, 
                            time_in as time, 
                            date, 
                            status as status 
                        FROM attendance 
                        WHERE user_id = :uid AND date >= :start_date
                        
                        UNION ALL
                        
                        -- Ambil Data PULANG
                        SELECT 
                            'Absen Pulang' as type, 
                            time_out as time, 
                            date, 
                            status_out as status 
                        FROM attendance 
                        WHERE user_id = :uid AND time_out IS NOT NULL AND date >= :start_date
                      ) AS combined_data
                      ORDER BY date DESC, time DESC";

    $stmtHistory = $conn->prepare($historyQuery);
    $stmtHistory->bindParam(':uid', $user_id);
    $stmtHistory->bindParam(':start_date', $yesterday); // Filter tanggal (Kemarin & Hari ini)
    $stmtHistory->execute();

    $historyData = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    // =================================================================================
    // BAGIAN 4: FINAL RESPONSE
    // =================================================================================

    echo json_encode([
        "success" => true,
        "status_absensi" => $currentStatus,
        "data_hari_ini" => $todayData,
        "history" => $historyData
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>