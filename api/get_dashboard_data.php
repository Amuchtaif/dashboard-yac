<?php
// 1. Setup Standar
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// 2. Validasi Input
if (!isset($_GET['user_id'])) {
    echo json_encode(["success" => false, "message" => "User ID diperlukan"]);
    exit();
}

$user_id = $_GET['user_id'];

// --- KONFIGURASI TANGGAL (PENTING UNTUK TESTING) ---
// Gunakan date('Y-m-d') untuk production. 
// Ubah manual string tanggalnya jika ingin simulasi hari libur/minggu.
$today = date('Y-m-d');
// $today = '2026-01-18'; // Uncomment baris ini untuk simulasi hari Minggu

$yesterday = date('Y-m-d', strtotime($today . " -1 days")); // Hitung tanggal kemarin relatif terhadap $today

try {
    // =================================================================================
    // BAGIAN 1: LAZY AUTO-CLOSE (Membersihkan Data Kemarin yang Lupa Pulang)
    // =================================================================================

    $cleanupQuery = "UPDATE attendances 
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
                    FROM attendances 
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

    $historyQuery = "SELECT type, time, date, status FROM (
                        -- Ambil Data MASUK
                        SELECT 
                            'Absen Masuk' as type, 
                            time_in as time, 
                            date, 
                            status as status 
                        FROM attendances 
                        WHERE user_id = :uid AND date >= :start_date
                        
                        UNION ALL
                        
                        -- Ambil Data PULANG
                        SELECT 
                            'Absen Pulang' as type, 
                            time_out as time, 
                            date, 
                            status_out as status 
                        FROM attendances 
                        WHERE user_id = :uid AND time_out IS NOT NULL AND date >= :start_date
                      ) AS combined_data
                      ORDER BY date DESC, time DESC";

    $stmtHistory = $conn->prepare($historyQuery);
    $stmtHistory->bindParam(':uid', $user_id);
    $stmtHistory->bindParam(':start_date', $yesterday);
    $stmtHistory->execute();

    $historyData = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    // =================================================================================
    // BAGIAN 3.5: GET TODAY'S SCHEDULE (REVISI HIERARKI YANG BENAR)
    // =================================================================================

    // Gunakan strtotime($today) agar sinkron jika Anda mengubah $today untuk testing
    $dayName = date('l', strtotime($today));

    // 1. Ambil Info Hierarki Karyawan
    $stmtEmp = $conn->prepare("SELECT schedule_id, unit_id, division_id FROM employees WHERE id = :uid");
    $stmtEmp->bindParam(':uid', $user_id);
    $stmtEmp->execute();
    $empInfo = $stmtEmp->fetch(PDO::FETCH_ASSOC);

    $final_schedule_id = null;

    if ($empInfo) {
        // Cek Prioritas 1: Personal Schedule
        if (!empty($empInfo['schedule_id'])) {
            $final_schedule_id = $empInfo['schedule_id'];
        }

        // Cek Prioritas 2: Unit Schedule (Ini yang hilang di kode lama Anda)
        if (!$final_schedule_id && !empty($empInfo['unit_id'])) {
            $stmtUnit = $conn->prepare("SELECT schedule_id FROM units WHERE id = ?");
            $stmtUnit->execute([$empInfo['unit_id']]);
            $unitData = $stmtUnit->fetch(PDO::FETCH_ASSOC);
            if ($unitData && !empty($unitData['schedule_id'])) {
                $final_schedule_id = $unitData['schedule_id'];
            }
        }

        // Cek Prioritas 3: Division Schedule
        if (!$final_schedule_id && !empty($empInfo['division_id'])) {
            $stmtDiv = $conn->prepare("SELECT schedule_id FROM divisions WHERE id = ?");
            $stmtDiv->execute([$empInfo['division_id']]);
            $divData = $stmtDiv->fetch(PDO::FETCH_ASSOC);
            if ($divData && !empty($divData['schedule_id'])) {
                $final_schedule_id = $divData['schedule_id'];
            }
        }
    }

    // Default jika tidak ada yang cocok
    if (!$final_schedule_id) {
        $final_schedule_id = 1;
    }

    // 2. Ambil Detail Jam Kerja Berdasarkan Hari
    $querySchedDetails = "SELECT start_time, end_time, is_day_off 
                          FROM work_schedule_details 
                          WHERE schedule_id = ? AND day_name = ?";

    $stmtSched = $conn->prepare($querySchedDetails);
    $stmtSched->execute([$final_schedule_id, $dayName]);
    $scheduleData = $stmtSched->fetch(PDO::FETCH_ASSOC);

    // 3. Format String Output untuk Frontend
    $scheduleString = "Tidak Ada Jadwal"; // Default jika data kosong

    if ($scheduleData) {
        if ($scheduleData['is_day_off'] == 1) {
            $scheduleString = "Libur";
        } elseif (!empty($scheduleData['start_time']) && !empty($scheduleData['end_time'])) {
            // Ubah format 08:00:00 menjadi 08:00 (Format Indonesia 24 jam)
            $start = date("H:i", strtotime($scheduleData['start_time']));
            $end = date("H:i", strtotime($scheduleData['end_time']));
            $scheduleString = "$start - $end";
        }
    }

    // =================================================================================
    // BAGIAN 4: FINAL RESPONSE
    // =================================================================================

    echo json_encode([
        "success" => true,
        "status_absensi" => $currentStatus,
        "today_schedule" => $scheduleString, // <-- Ini yang dipakai di Frontend
        "data_hari_ini" => $todayData,
        "history" => $historyData
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>