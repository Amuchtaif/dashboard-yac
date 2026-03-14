<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$json = file_get_contents("php://input");
$data = json_decode($json);

// 1. Validasi Input Dasar
if (!isset($data->user_id) || !isset($data->type) || !isset($data->latitude) || !isset($data->longitude)) {
    echo json_encode(["success" => false, "message" => "Data tidak lengkap (Butuh Lokasi)"]);
    exit();
}

$user_id = $data->user_id;
$type = strtoupper($data->type);
$user_lat = $data->latitude;
$user_long = $data->longitude;
$now_time = date('H:i:s');

// =================================================================================
// AREA CONFIG TESTING (SIMULASI TANGGAL)
// =================================================================================

// [OPSI 1: PRODUCTION] Gunakan ini untuk penggunaan normal (Realtime)
$today = date('Y-m-d');

// [OPSI 2: TESTING] Hapus tanda // di bawah ini untuk tes hari Minggu atau tanggal lain
// $today = '2026-01-18'; // Contoh: Tanggal 18 Jan 2026 adalah MINGGU

// =================================================================================

// 2. LOGIC HARI DINAMIS
// Mengambil nama hari (Monday, Sunday, dll) BERDASARKAN variabel $today.
// Ini kuncinya agar saat Anda hardcode $today, validasi jadwalnya ikut berubah.
$currentDayName = date('l', strtotime($today));



// --- FUNGSI HITUNG JARAK (Haversine) ---
function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c);
}

try {
    // 3. CEK LOKASI KANTOR & RADIUS
    $location_id = isset($data->location_id) ? $data->location_id : null;
    
    if ($location_id) {
        $officeQuery = "SELECT latitude, longitude, radius_meter FROM locations WHERE id = :lid AND is_active = 1";
        $stmtOffice = $conn->prepare($officeQuery);
        $stmtOffice->bindParam(':lid', $location_id);
    } else {
        $officeQuery = "SELECT latitude, longitude, radius_meter FROM locations WHERE is_active = 1 LIMIT 1";
        $stmtOffice = $conn->prepare($officeQuery);
    }
    
    $stmtOffice->execute();
    $office = $stmtOffice->fetch(PDO::FETCH_ASSOC);

    if (!$office) {
        // Fallback default jika tidak ditemukan
        $office = ['latitude' => '-6.200000', 'longitude' => '106.816666', 'radius_meter' => 100];
    }

    // Hitung Jarak
    $distance = calculateDistance($user_lat, $user_long, $office['latitude'], $office['longitude']);

    // Validasi Geofencing
    if ($distance > $office['radius_meter']) {
        echo json_encode([
            "success" => false,
            "message" => "Diluar jangkauan kantor! Jarak: $distance m (Max: {$office['radius_meter']} m)"
        ]);
        exit();
    }

    // 4. AMBIL DATA KARYAWAN & SCHEDULE ID
    $stmtEmployee = $conn->prepare("SELECT schedule_id, division_id, unit_id FROM employees WHERE id = :uid");
    $stmtEmployee->bindParam(':uid', $user_id);
    $stmtEmployee->execute();
    $employee = $stmtEmployee->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        echo json_encode(["success" => false, "message" => "Karyawan tidak ditemukan"]);
        exit();
    }

    // --- LOGIKA TUKAR SHIFT (OPSI A) ---
    // Cek apakah ada permohonan tukar shift yang disetujui untuk user ini di tanggal $today
    $stmtSwap = $conn->prepare("SELECT requester_id, substitute_id FROM shift_exchanges WHERE (requester_id = ? OR substitute_id = ?) AND exchange_date = ? AND status = 'Disetujui' LIMIT 1");
    $stmtSwap->execute([$user_id, $user_id, $today]);
    $swap = $stmtSwap->fetch(PDO::FETCH_ASSOC);

    $is_swapped = false;
    if ($swap) {
        // Jika ada swap, User ini akan "meminjam" profil partner-nya untuk menentukan jadwal (Schedule ID)
        $partner_id = ($swap['requester_id'] == $user_id) ? $swap['substitute_id'] : $swap['requester_id'];
        $is_swapped = true;
        
        // Ambil data partner untuk mendapatkan hierarki jadwalnya
        $stmtPartner = $conn->prepare("SELECT schedule_id, division_id, unit_id FROM employees WHERE id = ?");
        $stmtPartner->execute([$partner_id]);
        $partnerData = $stmtPartner->fetch(PDO::FETCH_ASSOC);
        
        if ($partnerData) {
            $employee = $partnerData; // Gunakan profil partner untuk perhitungan jadwal di bawah
        }
    }

    // --- LOGIKA HIERARKI JADWAL ---
    // Prioritas: Personal > Unit > Division > Default (ID:1)
    $schedule_id = null;

    // A. Cek Jadwal Personal
    if (!empty($employee['schedule_id'])) {
        $schedule_id = $employee['schedule_id'];
    }

    // B. Cek Jadwal Unit
    if (!$schedule_id && !empty($employee['unit_id'])) {
        $stmtUnit = $conn->prepare("SELECT schedule_id FROM units WHERE id = ?");
        $stmtUnit->execute([$employee['unit_id']]);
        $unit = $stmtUnit->fetch(PDO::FETCH_ASSOC);
        if ($unit && !empty($unit['schedule_id'])) {
            $schedule_id = $unit['schedule_id'];
        }
    }

    // C. Cek Jadwal Divisi
    if (!$schedule_id && !empty($employee['division_id'])) {
        $stmtDiv = $conn->prepare("SELECT schedule_id FROM divisions WHERE id = ?");
        $stmtDiv->execute([$employee['division_id']]);
        $division = $stmtDiv->fetch(PDO::FETCH_ASSOC);
        if ($division && !empty($division['schedule_id'])) {
            $schedule_id = $division['schedule_id'];
        }
    }

    // D. Fallback ke Default
    if (!$schedule_id) {
        $schedule_id = 1;
    }

    // 5. CEK DETAIL JADWAL BERDASARKAN HARI ($today)
    $stmtSched = $conn->prepare("SELECT * FROM work_schedule_details WHERE schedule_id = ? AND day_name = ?");
    $stmtSched->execute([$schedule_id, $currentDayName]);
    $dailySched = $stmtSched->fetch(PDO::FETCH_ASSOC);

    // --- VALIDASI HARI KERJA (FIX BUG MINGGU) ---

    if (!$dailySched) {
        echo json_encode([
            'success' => false,
            'message' => "Jadwal tidak ditemukan untuk hari ini. Absen ditolak."
        ]);
        exit();
    }

    if ($dailySched['is_day_off'] == 1) {
        echo json_encode([
            'success' => false,
            'message' => "Absen Ditolak: Hari ini adalah hari libur."
        ]);
        exit();
    }

    if (empty($dailySched['start_time']) || $dailySched['start_time'] == '00:00:00') {
        echo json_encode([
            'success' => false,
            'message' => "Absen Ditolak: Jam kerja belum diatur untuk hari ini."
        ]);
        exit();
    }

    // Set Jam Masuk & Pulang dari Database
    $jam_masuk_kantor = $dailySched['start_time'];
    $jam_pulang_kantor = $dailySched['end_time'];

    // --- LOGIKA OVERRIDE RAMADAN ---
    $stmtRamadan = $conn->query("SELECT is_active FROM ramadan_settings WHERE id = 1 LIMIT 1");
    $ramadan = $stmtRamadan->fetch(PDO::FETCH_ASSOC);

    if ($ramadan && (int)$ramadan['is_active'] === 1) {
        if (!empty($employee['unit_id'])) {
            // Find an override rule that covers this day ($currentDayName) and this specific unit ($employee['unit_id'])
            $stmtOverride = $conn->prepare("SELECT start_time, end_time FROM ramadan_overrides 
                                          WHERE FIND_IN_SET(?, days) 
                                          AND FIND_IN_SET(?, unit_ids)
                                          ORDER BY id DESC LIMIT 1");
            $stmtOverride->execute([$currentDayName, $employee['unit_id']]);
            $override = $stmtOverride->fetch(PDO::FETCH_ASSOC);

            if ($override) {
                if (!empty($override['start_time'])) {
                    $jam_masuk_kantor = $override['start_time'];
                }
                if (!empty($override['end_time'])) {
                    $jam_pulang_kantor = $override['end_time'];
                }
                $is_ramadan_override = true;
            }
        }
    }


    // 6. PROSES INSERT / UPDATE DATABASE

    if ($type == "IN") {
        // Cek Double Login
        $checkQuery = "SELECT id FROM attendances WHERE user_id = :uid AND date = :date";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bindParam(':uid', $user_id);
        $stmt->bindParam(':date', $today);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Anda sudah absen masuk hari ini!"]);
        } else {
            // Tentukan Status (Telat / Hadir)
            $status_in = ($now_time > $jam_masuk_kantor) ? "Telat" : "Hadir";

            // Query Insert
            $insertQuery = "INSERT INTO attendances 
                            (user_id, location_id, date, time_in, status, lat_in, long_in) 
                            VALUES (:uid, :lid, :date, :time, :stat, :lat, :long)";

            $stmtInsert = $conn->prepare($insertQuery);
            $stmtInsert->bindParam(':uid', $user_id);
            $stmtInsert->bindParam(':lid', $location_id);
            $stmtInsert->bindParam(':date', $today);
            $stmtInsert->bindParam(':time', $now_time);
            $stmtInsert->bindParam(':stat', $status_in);
            $stmtInsert->bindParam(':lat', $user_lat);
            $stmtInsert->bindParam(':long', $user_long);

            if ($stmtInsert->execute()) {
                $swapMsg = $is_swapped ? " (Tukar Shift)" : "";
                echo json_encode([
                    "success" => true,
                    "message" => "Absen Masuk Berhasil$swapMsg ($status_in). Jarak: $distance m"
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Gagal simpan database"]);
            }
        }

    } elseif ($type == "OUT") {
        // Cek Data Absen Masuk
        $checkQuery = "SELECT id, time_out FROM attendances WHERE user_id = :uid AND date = :date";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bindParam(':uid', $user_id);
        $stmt->bindParam(':date', $today);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(["success" => false, "message" => "Belum absen masuk!"]);
        } elseif ($row['time_out'] != null) {
            echo json_encode(["success" => false, "message" => "Sudah absen pulang!"]);
        } else {
            // Tentukan Status Pulang
            $status_out = ($now_time < $jam_pulang_kantor) ? "Pulang Cepat" : "Pulang";

            // Query Update
            $updateQuery = "UPDATE attendances 
                            SET time_out = :time, 
                                status_out = :stat_out, 
                                lat_out = :lat, 
                                long_out = :long 
                            WHERE id = :id";

            $stmtUpdate = $conn->prepare($updateQuery);
            $stmtUpdate->bindParam(':time', $now_time);
            $stmtUpdate->bindParam(':stat_out', $status_out);
            $stmtUpdate->bindParam(':lat', $user_lat);
            $stmtUpdate->bindParam(':long', $user_long);
            $stmtUpdate->bindParam(':id', $row['id']);

            if ($stmtUpdate->execute()) {
                $swapMsg = $is_swapped ? " (Tukar Shift)" : "";
                echo json_encode([
                    "success" => true,
                    "message" => "Absen Pulang Berhasil$swapMsg ($status_out). Jarak: $distance m"
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Gagal simpan pulang"]);
            }
        }
    } else {
        echo json_encode(["success" => false, "message" => "Tipe absen salah"]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>