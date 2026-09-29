<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$json = file_get_contents("php://input");
$data = json_decode($json);

// --- FUNGSI HITUNG JARAK (Haversine) ---
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meter
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

try {
    // 1. Validasi input
    if (empty($data->user_id) || empty($data->location_id) || !isset($data->latitude) || !isset($data->longitude)) {
        throw new Exception("Data tidak lengkap (user_id, location_id, latitude, longitude required)");
    }

    $user_id = (int)$data->user_id;
    $location_id = (int)$data->location_id;
    $latitude = (float)$data->latitude;
    $longitude = (float)$data->longitude;
    $today = date('Y-m-d');
    $now_time = date('H:i:s');

    // 2. Cek apakah user sudah absen hari ini
    $check_query = "SELECT id FROM attendances WHERE user_id = :user_id AND date = :date LIMIT 1";
    $stmt_check = $db->prepare($check_query);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->bindParam(':date', $today);
    $stmt_check->execute();

    if ($stmt_check->rowCount() > 0) {
        throw new Exception("Anda sudah melakukan absensi hari ini");
    }

    // 3. Ambil data lokasi berdasarkan location_id
    $loc_query = "SELECT latitude, longitude, radius_meter FROM locations WHERE id = :loc_id AND is_active = 1 LIMIT 1";
    $stmt_loc = $db->prepare($loc_query);
    $stmt_loc->bindParam(':loc_id', $location_id);
    $stmt_loc->execute();
    $location = $stmt_loc->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        throw new Exception("Lokasi tidak ditemukan atau tidak aktif");
    }

    // 4. Hitung jarak dengan rumus Haversine
    $distance = calculateDistance($latitude, $longitude, $location['latitude'], $location['longitude']);

    // 5. Jika jarak > radius_meter → tolak
    if ($distance > $location['radius_meter']) {
        throw new Exception("Anda berada di luar radius lokasi yang ditentukan (Jarak: " . round($distance, 2) . "m)");
    }

    // 5b. Ambil jadwal kerja karyawan untuk validasi Batas Awal Absen (Earliest Check-In)
    $stmtEmp = $db->prepare("SELECT schedule_id, division_id, unit_id FROM employees WHERE id = :uid");
    $stmtEmp->bindParam(':uid', $user_id);
    $stmtEmp->execute();
    $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);

    $currentDayName = date('l', strtotime($today));
    $schedule_id = null;
    if ($employee) {
        if (!empty($employee['schedule_id'])) { $schedule_id = $employee['schedule_id']; }
        if (!$schedule_id && !empty($employee['unit_id'])) {
            $stmtUnit = $db->prepare("SELECT schedule_id FROM units WHERE id = ?");
            $stmtUnit->execute([$employee['unit_id']]);
            $unit = $stmtUnit->fetch(PDO::FETCH_ASSOC);
            if ($unit && !empty($unit['schedule_id'])) { $schedule_id = $unit['schedule_id']; }
        }
        if (!$schedule_id && !empty($employee['division_id'])) {
            $stmtDiv = $db->prepare("SELECT schedule_id FROM divisions WHERE id = ?");
            $stmtDiv->execute([$employee['division_id']]);
            $division = $stmtDiv->fetch(PDO::FETCH_ASSOC);
            if ($division && !empty($division['schedule_id'])) { $schedule_id = $division['schedule_id']; }
        }
    }
    if (!$schedule_id) { $schedule_id = 1; }

    $stmtSched = $db->prepare("SELECT * FROM work_schedule_details WHERE schedule_id = ? AND day_name = ?");
    $stmtSched->execute([$schedule_id, $currentDayName]);
    $dailySched = $stmtSched->fetch(PDO::FETCH_ASSOC);

    if ($dailySched && (int)$dailySched['is_day_off'] === 1) {
        throw new Exception("Hari libur. Absen masuk ditolak.");
    }

    $jam_masuk_kantor = ($dailySched && !empty($dailySched['start_time'])) ? $dailySched['start_time'] : '08:00:00';

    // Batas Awal Absen (Earliest Check-In): Maksimal 60 menit sebelum shift
    $earliest_checkin_minutes = 60;
    $earliest_time = date('H:i:s', strtotime($jam_masuk_kantor . " -{$earliest_checkin_minutes} minutes"));
    $jam_masuk_toleransi = date('H:i:s', strtotime($jam_masuk_kantor . ' +1 minute'));

    if ($now_time < $earliest_time) {
        // Absen lebih awal dari 60 menit sebelum jadwal -> Diizinkan, tetapi status khusus dan tidak mendapat poin
        $status = "Hadir Diluar Batas";
    } elseif ($now_time >= $jam_masuk_toleransi) {
        $status = "Telat";
    } else {
        $status = "Hadir";
    }
    
    $insert_query = "INSERT INTO attendances (user_id, location_id, date, time_in, lat_in, long_in, status) 
                    VALUES (:user_id, :location_id, :date, :time_in, :lat_in, :long_in, :status)";
    $stmt_insert = $db->prepare($insert_query);
    $stmt_insert->bindParam(':user_id', $user_id);
    $stmt_insert->bindParam(':location_id', $location_id);
    $stmt_insert->bindParam(':date', $today);
    $stmt_insert->bindParam(':time_in', $now_time);
    $stmt_insert->bindParam(':lat_in', $latitude);
    $stmt_insert->bindParam(':long_in', $longitude);
    $stmt_insert->bindParam(':status', $status);

    if ($stmt_insert->execute()) {
        $extra = ($status === "Hadir Diluar Batas") ? " (Diluar batas waktu, 0 poin)" : "";
        echo json_encode([
            "status" => true,
            "message" => "Check-in berhasil ($status)$extra"
        ]);
    } else {
        throw new Exception("Gagal menyimpan data absensi");
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>
