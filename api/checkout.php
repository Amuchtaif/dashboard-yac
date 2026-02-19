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
    // Validasi input minimal
    if (empty($data->user_id) || !isset($data->latitude) || !isset($data->longitude)) {
        throw new Exception("Data tidak lengkap (user_id, latitude, longitude required)");
    }

    $user_id = (int)$data->user_id;
    $latitude = (float)$data->latitude;
    $longitude = (float)$data->longitude;
    $today = date('Y-m-d');
    $now_time = date('H:i:s');

    // 1. Ambil attendance hari ini
    $query_att = "SELECT id, location_id, time_out FROM attendances WHERE user_id = :user_id AND date = :date LIMIT 1";
    $stmt_att = $db->prepare($query_att);
    $stmt_att->bindParam(':user_id', $user_id);
    $stmt_att->bindParam(':date', $today);
    $stmt_att->execute();
    $attendance = $stmt_att->fetch(PDO::FETCH_ASSOC);

    if (!$attendance) {
        throw new Exception("Belum melakukan check-in hari ini");
    }

    if ($attendance['time_out'] != null) {
        throw new Exception("Anda sudah melakukan check-out hari ini");
    }

    $location_id = $attendance['location_id'];

    // 3. Ambil koordinat & radius dari tabel lokasi
    $query_loc = "SELECT latitude, longitude, radius_meter FROM locations WHERE id = :loc_id LIMIT 1";
    $stmt_loc = $db->prepare($query_loc);
    $stmt_loc->bindParam(':loc_id', $location_id);
    $stmt_loc->execute();
    $location = $stmt_loc->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        throw new Exception("Data lokasi tidak ditemukan");
    }

    // 4. Hitung jarak
    $distance = calculateDistance($latitude, $longitude, $location['latitude'], $location['longitude']);

    // 5. Validasi radius
    if ($distance > $location['radius_meter']) {
        throw new Exception("Anda berada di luar radius lokasi (Jarak: " . round($distance, 2) . "m)");
    }

    // 6. Update time_out, lat_out, long_out
    $status_out = "Pulang"; // Default
    
    $update_query = "UPDATE attendances SET 
                    time_out = :time_out, 
                    lat_out = :lat_out, 
                    long_out = :long_out, 
                    status_out = :status_out 
                    WHERE id = :id";
    $stmt_update = $db->prepare($update_query);
    $stmt_update->bindParam(':time_out', $now_time);
    $stmt_update->bindParam(':lat_out', $latitude);
    $stmt_update->bindParam(':long_out', $longitude);
    $stmt_update->bindParam(':status_out', $status_out);
    $stmt_update->bindParam(':id', $attendance['id']);

    if ($stmt_update->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Check-out berhasil"
        ]);
    } else {
        throw new Exception("Gagal mengupdate data absensi");
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
