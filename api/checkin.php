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

    // 6. Jika valid → insert ke attendances
    // Menentukan status (Hadir/Telat) - Opsional, user tidak minta logika telat tapi API sebelumnya punya.
    // Di sini saya ikuti permintaan user untuk field minimal.
    $status = "Hadir"; // Default
    
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
        echo json_encode([
            "status" => true,
            "message" => "Check-in berhasil"
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
