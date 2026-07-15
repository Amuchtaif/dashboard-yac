<?php
// api/tahfidz/submit_memorization.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

$input = json_decode(file_get_contents("php://input"), true) ?? [];

$student_id = isset($input['student_id']) ? $input['student_id'] : null;
$date = isset($input['date']) ? $input['date'] : date('Y-m-d');
$surah_start = isset($input['surah_start']) ? $input['surah_start'] : '';
$ayat_start = isset($input['ayat_start']) ? $input['ayat_start'] : 0;
$total_baris = isset($input['total_baris']) ? $input['total_baris'] : 0;
$juz = isset($input['juz']) ? $input['juz'] : null;
$status = isset($input['status']) && !empty($input['status']) ? $input['status'] : (isset($input['score']) && !is_numeric($input['score']) && in_array($input['score'], ['Lancar', 'Kurang', 'Tidak', 'Kurang Lancar', 'Ulang', 'Ziyadah', 'Murajaah']) ? $input['score'] : 'Lancar'); // Lancar, Kurang Lancar, Ulang, etc.
$notes = isset($input['notes']) ? $input['notes'] : '';
$teacher_id = isset($input['teacher_id']) ? $input['teacher_id'] : null;

// Auto-detect Juz from Quran API if not provided or to ensure accuracy
if ($surah_start && $ayat_start) {
    // Only attempt if surah_start is numeric (Surah ID)
    if (is_numeric($surah_start)) {
        $url = "https://api.alquran.cloud/v1/ayah/{$surah_start}:{$ayat_start}";
        
        $api_res = null;
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $api_res = curl_exec($ch);
            curl_close($ch);
        } else {
            $api_res = @file_get_contents($url);
        }

        if ($api_res) {
            $api_data = json_decode($api_res, true);
            if (isset($api_data['data']['juz'])) {
                $juz = $api_data['data']['juz'];
            }
        }
    }
}

if (!$student_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Student ID is required"]);
    exit;
}

// Check Permission
include_once __DIR__ . '/../../config/permission.php';
if ($teacher_id && !hasPermission($teacher_id, 'access_tahfidz')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Forbidden: Anda tidak memiliki akses Tahfidz."]);
    exit;
}

try {
    // Default surah_end and ayat_end if not provided
    $surah_end = isset($input['surah_end']) ? $input['surah_end'] : $surah_start;
    $ayat_end = isset($input['ayat_end']) ? $input['ayat_end'] : $ayat_start;

    // Lookup Surah Names for backward compatibility columns
    $surah_start_name = "";
    $surah_end_name = "";
    $surat_json_path = __DIR__ . '/../quran/surat.json';
    if (file_exists($surat_json_path)) {
        $surat_data = json_decode(file_get_contents($surat_json_path), true);
        if (isset($surat_data['data'])) {
            foreach ($surat_data['data'] as $surah) {
                if ($surah['nomor'] == $surah_start) {
                    $surah_start_name = $surah['namaLatin'];
                }
                if ($surah['nomor'] == $surah_end) {
                    $surah_end_name = $surah['namaLatin'];
                }
            }
        }
    }
    if (empty($surah_start_name) && is_numeric($surah_start)) {
        $surah_start_name = "Surah " . $surah_start;
    }
    if (empty($surah_end_name)) {
        $surah_end_name = $surah_start_name;
    }

    $entry_type = 'HAFALAN_BARU';
    if (isset($input['entry_type']) && !empty($input['entry_type'])) {
        $entry_type = strtoupper($input['entry_type']);
    } elseif (isset($input['jenis_setoran']) && !empty($input['jenis_setoran'])) {
        $entry_type = strtoupper($input['jenis_setoran']);
    } else {
        $entry_type = (strcasecmp($status, 'Murajaah') === 0) ? 'MUROJAAH' : 'HAFALAN_BARU';
    }

    $stmt = $mysqli->prepare("INSERT INTO memorization_entries 
        (student_id, date, entry_type, start_surah_id, start_ayah, end_surah_id, end_ayah, line_count, notes, teacher_id, surah_id, surah_start, surah_end, total_baris, juz, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $start_surah_id = (int)$surah_start;
    $end_surah_id = (int)$surah_end;
    $surah_id = $start_surah_id;

    $stmt->bind_param("issiiiiissiissis", 
        $student_id, 
        $date, 
        $entry_type,
        $start_surah_id,
        $ayat_start,
        $end_surah_id,
        $ayat_end,
        $total_baris,
        $notes,
        $teacher_id,
        $surah_id,
        $surah_start_name,
        $surah_end_name,
        $total_baris,
        $juz,
        $status
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Memorization record saved successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to save record: " . $stmt->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
