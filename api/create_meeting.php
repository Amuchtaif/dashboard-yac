<?php
// api/create_meeting.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../config/db_mysqli.php';

// Cek Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

// 1. Ambil Data Input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// Support both JSON body and Form Data, prioritize JSON body if available
$title = $input['title'] ?? $_POST['title'] ?? '';
$description = $input['description'] ?? $_POST['description'] ?? '';
$meeting_date = $input['date'] ?? $_POST['date'] ?? '';
$start_time = $input['start_time'] ?? $_POST['start_time'] ?? '';
$end_time = $input['end_time'] ?? $_POST['end_time'] ?? '';
$type = $input['type'] ?? $_POST['type'] ?? ''; // online, offline
$location = $input['location'] ?? $_POST['location'] ?? '';
$created_by = $input['created_by'] ?? $_POST['created_by'] ?? '';
$division_id = $input['division_id'] ?? $_POST['division_id'] ?? '';
$participant_ids = $input['participant_ids'] ?? $_POST['participant_ids'] ?? [];

// Jika participant_ids dikirim sebagai string (misal dari form-data: "[1,2,3]" atau "1,2,3")
if (!is_array($participant_ids)) {
    // Coba decode json
    $decoded = json_decode($participant_ids, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $participant_ids = $decoded;
    } else {
        // Coba explode jika koma separated
        $participant_ids = explode(',', $participant_ids);
    }
}

// 2. Validasi
if (empty($title) || empty($meeting_date) || empty($start_time) || empty($end_time) || empty($type) || empty($created_by) || empty($division_id)) {
    echo json_encode(["success" => false, "message" => "Kolom wajib diisi: title, date, start_time, end_time, type, created_by, division_id."]);
    exit();
}

// Validasi Type Enum
if (!in_array($type, ['online', 'offline'])) {
    echo json_encode(["success" => false, "message" => "Type harus 'online' atau 'offline'."]);
    exit();
}

// 3. Generate Token Unik
$qr_token = uniqid('MEET-');

// 4. Proses Insert (Transaction)
$mysqli->begin_transaction();

try {
    // A. Insert ke tabel meetings
    $sqlMeeting = "INSERT INTO meetings (title, description, meeting_date, start_time, end_time, type, location, created_by, division_id, qr_token) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sqlMeeting);
    $stmt->bind_param(
        "sssssssiis",
        $title,
        $description,
        $meeting_date,
        $start_time,
        $end_time,
        $type,
        $location,
        $created_by,
        $division_id,
        $qr_token
    );

    if (!$stmt->execute()) {
        throw new Exception("Gagal membuat meeting: " . $stmt->error);
    }

    $meeting_id = $mysqli->insert_id;

    // B. Insert ke tabel meeting_participants
    // Sertakan creator sebagai peserta juga? Biasanya ya, atau tergantung kebutuhan.
    // Di sini kita loop array participant_ids saja.

    if (!empty($participant_ids)) {
        $sqlParticipant = "INSERT INTO meeting_participants (meeting_id, employee_id, status) VALUES (?, ?, 'invited')";
        $stmtPart = $mysqli->prepare($sqlParticipant);

        foreach ($participant_ids as $p_id) {
            $p_id_clean = trim($p_id);
            if (!empty($p_id_clean)) {
                $stmtPart->bind_param("ii", $meeting_id, $p_id_clean);
                if (!$stmtPart->execute()) {
                    // Opsional: throw error atau skip
                    throw new Exception("Gagal menambahkan peserta ID: $p_id_clean - " . $stmtPart->error);
                }
            }
        }
    }

    // Commit Transaction
    $mysqli->commit();

    echo json_encode([
        "success" => true,
        "message" => "Meeting berhasil dibuat.",
        "meeting_id" => $meeting_id,
        "qr_token" => $qr_token
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan: " . $e->getMessage()
    ]);
}

$mysqli->close();
?>