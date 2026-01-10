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

// Validasi Input (Wajib kirim latitude & longitude dari Flutter)
if (!isset($data->user_id) || !isset($data->type) || !isset($data->latitude) || !isset($data->longitude)) {
    echo json_encode(["success" => false, "message" => "Data tidak lengkap (Butuh Lokasi)"]);
    exit();
}

$user_id = $data->user_id;
$type = strtoupper($data->type);
$user_lat = $data->latitude;   // Koordinat dari HP saat ini
$user_long = $data->longitude; // Koordinat dari HP saat ini
$today = date('Y-m-d');
$now_time = date('H:i:s');

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
    // 1. CEK LOKASI KANTOR & RADIUS
    $officeQuery = "SELECT latitude, longitude, radius_meters FROM office_settings LIMIT 1";
    $stmtOffice = $conn->prepare($officeQuery);
    $stmtOffice->execute();
    $office = $stmtOffice->fetch(PDO::FETCH_ASSOC);

    if (!$office) {
        // Jika belum diset, anggap lolos dulu atau error (tergantung kebijakan)
        // echo json_encode(["success" => false, "message" => "Lokasi kantor belum disetting admin"]); exit();
        // Untuk demo, kita hardcode default jika kosong:
        $office = ['latitude' => '-6.200000', 'longitude' => '106.816666', 'radius_meters' => 100000];
    }

    // 2. HITUNG JARAK
    $distance = calculateDistance($user_lat, $user_long, $office['latitude'], $office['longitude']);

    // GEOFENCING: Tolak jika diluar radius
    if ($distance > $office['radius_meters']) {
        echo json_encode([
            "success" => false,
            "message" => "Diluar jangkauan kantor! Jarak: $distance m (Max: {$office['radius_meters']} m)"
        ]);
        exit();
    }

    // Fetch employee details first to get schedule_id and department_id
    $stmtEmployee = $conn->prepare("SELECT schedule_id, department_id FROM employees WHERE id = :uid");
    $stmtEmployee->bindParam(':uid', $user_id);
    $stmtEmployee->execute();
    $employee = $stmtEmployee->fetch(PDO::FETCH_ASSOC);

    // Default jam masuk kantor
    $jam_masuk_kantor = "08:00:00";

    // --- 2. Check Work Schedule (Day-Based) ---
    $currentDayName = date('l'); // e.g., "Monday"

    // Determine Schedule ID (Employee > Department)
    $schedule_id = $employee['schedule_id'] ?? null;
    if (!$schedule_id && $employee['department_id']) {
        $stmtDept = $conn->prepare("SELECT schedule_id FROM departments WHERE id = ?");
        $stmtDept->execute([$employee['department_id']]);
        $dept = $stmtDept->fetch(PDO::FETCH_ASSOC);
        $schedule_id = $dept['schedule_id'] ?? null;
    }

    // Fallback to default schedule_id if none found (e.g., schedule_id = 1 for a general schedule)
    if (!$schedule_id) {
        $schedule_id = 1; // Assuming 1 is a default/general schedule ID
    }

    if ($schedule_id) {
        // Fetch Daily Schedule Detail
        $stmtSched = $conn->prepare("SELECT * FROM work_schedule_details WHERE schedule_id = ? AND day_name = ?");
        $stmtSched->execute([$schedule_id, $currentDayName]);
        $dailySched = $stmtSched->fetch(PDO::FETCH_ASSOC);

        if ($dailySched) {
            if ($dailySched['is_day_off'] == 1) {
                echo json_encode(['success' => false, 'message' => "Today is a Day Off ($currentDayName)."]);
                exit;
            }
            if ($dailySched['start_time']) {
                $jam_masuk_kantor = $dailySched['start_time'];
            }
            // End time usage if needed later
        } else {
            // Fallback or Error if no detail found for the day?
            // Maybe default to 08:00 if data is missing, or return error.
            // For safety, assume default if missing, or strict error.
            // Let's keep existing $jam_masuk_kantor = "08:00:00" as ultimate fallback if not set.
        }
    }

    // --- PROSES ABSEN ---

    if ($type == "IN") {
        // Cek Double Login
        $checkQuery = "SELECT id FROM attendance WHERE user_id = :uid AND date = :date";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bindParam(':uid', $user_id);
        $stmt->bindParam(':date', $today);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Anda sudah absen masuk hari ini!"]);
        } else {
            // Tentukan Status
            $status_in = ($now_time > $jam_masuk_kantor) ? "Telat" : "Hadir";

            // INSERT: Masukkan lat_in dan long_in
            $insertQuery = "INSERT INTO attendance 
                            (user_id, date, time_in, status, lat_in, long_in) 
                            VALUES (:uid, :date, :time, :stat, :lat, :long)";

            $stmtInsert = $conn->prepare($insertQuery);
            $stmtInsert->bindParam(':uid', $user_id);
            $stmtInsert->bindParam(':date', $today);
            $stmtInsert->bindParam(':time', $now_time);
            $stmtInsert->bindParam(':stat', $status_in);
            $stmtInsert->bindParam(':lat', $user_lat);  // Masuk ke lat_in
            $stmtInsert->bindParam(':long', $user_long); // Masuk ke long_in

            if ($stmtInsert->execute()) {
                echo json_encode(["success" => true, "message" => "Absen Masuk Berhasil ($status_in). Jarak: $distance m"]);
            } else {
                echo json_encode(["success" => false, "message" => "Gagal simpan database"]);
            }
        }

    } elseif ($type == "OUT") {
        $checkQuery = "SELECT id, time_out FROM attendance WHERE user_id = :uid AND date = :date";
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
            // UPDATE: Masukkan lat_out dan long_out
            $updateQuery = "UPDATE attendance 
                            SET time_out = :time, 
                                status_out = 'Pulang', 
                                lat_out = :lat, 
                                long_out = :long 
                            WHERE id = :id";

            $stmtUpdate = $conn->prepare($updateQuery);
            $stmtUpdate->bindParam(':time', $now_time);
            $stmtUpdate->bindParam(':lat', $user_lat);  // Masuk ke lat_out
            $stmtUpdate->bindParam(':long', $user_long); // Masuk ke long_out
            $stmtUpdate->bindParam(':id', $row['id']);

            if ($stmtUpdate->execute()) {
                echo json_encode(["success" => true, "message" => "Absen Pulang Berhasil. Jarak: $distance m"]);
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