<?php
// api/kabid/staff_attendance/manual_save.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/config/app.php';

try {
    /** @var \Database $db */
    $db = new Database();
    $conn = $db->getConnection();

    $json = file_get_contents("php://input");
    $data = json_decode($json);

    if (!is_object($data) || !isset($data->kabid_id) || !isset($data->staff_id) || !isset($data->type) || !isset($data->date) || !isset($data->time)) {
        echo json_encode(["success" => false, "message" => "Parameter tidak lengkap."]);
        exit();
    }

    $kabid_id = $data->kabid_id;
    $staff_id = $data->staff_id;
    $type = strtoupper($data->type); // MASUK / PULANG
    $date = $data->date;
    $time = $data->time;
    $note = isset($data->note) ? $data->note : '';

    // 1. Verifikasi Staf adalah bawahan dengan kriteria:
    // - Division ID Sama
    // - Level 3 ATAU (Level 4+ dan unit_id Kosong)
    $stmtDiv = $conn->prepare("SELECT division_id FROM employees WHERE id = ? AND status = 'active'");
    $stmtDiv->execute([$kabid_id]);
    $kabid = $stmtDiv->fetch(PDO::FETCH_ASSOC);

    $kabid_division_id = ($kabid && isset($kabid['division_id'])) ? $kabid['division_id'] : 0;

    $stmtVal = $conn->prepare("
        SELECT e.id FROM employees e
        INNER JOIN positions p ON e.position_id = p.id
        WHERE e.id = ? AND e.division_id = ? AND e.status = 'active'
    ");
    $stmtVal->execute([$staff_id, $kabid_division_id]);
    $isValidSubordinate = $stmtVal->fetch();

    if (!$kabid || !$isValidSubordinate) {
        echo json_encode(["success" => false, "message" => "Gagal: Staf tidak ditemukan atau bukan merupakan bawahan langsung Anda sesuai kriteria level."]);
        exit();
    }

    // Ambil detail staf untuk proses selanjutnya
    $stmtStaff = $conn->prepare("SELECT schedule_id FROM employees WHERE id = ? AND status = 'active'");
    $stmtStaff->execute([$staff_id]);
    $staff = $stmtStaff->fetch(PDO::FETCH_ASSOC);

    $schedule_id = ($staff && isset($staff['schedule_id']) && $staff['schedule_id'] !== null) ? $staff['schedule_id'] : 1;

    // 2. Tentukan Status Berdasarkan Jadwal (Hadir / Telat / Pulang / Pulang Cepat)
    $dayName = date('l', strtotime($date));
    $stmtSched = $conn->prepare("SELECT start_time, end_time FROM work_schedule_details WHERE schedule_id = ? AND day_name = ?");
    $stmtSched->execute([$schedule_id, $dayName]);
    $sched = $stmtSched->fetch(PDO::FETCH_ASSOC);
    
    $finalStatus = "Hadir";
    if ($type == 'MASUK') {
        $start_time_tolerance = ($sched && !empty($sched['start_time'])) ? date('H:i:s', strtotime($sched['start_time'] . ' +1 minute')) : null;
        if ($sched && $start_time_tolerance && $time >= $start_time_tolerance) $finalStatus = "Telat";
        else $finalStatus = "Hadir";
    } else {
        if ($sched && !empty($sched['end_time']) && $time < $sched['end_time']) $finalStatus = "Pulang Cepat";
        else $finalStatus = "Pulang";
    }

    // 3. Proses Simpan
    // Cek apakah data di tanggal tersebut sudah ada
    $stmtCheck = $conn->prepare("SELECT id FROM attendances WHERE user_id = ? AND date = ?");
    $stmtCheck->execute([$staff_id, $date]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($type == 'MASUK') {
        if ($existing) {
            // Update time_in jika sudah ada tetapi time_in masih kosong (jarang terjadi tapi mungkin)
            $update = $conn->prepare("UPDATE attendances SET time_in = ?, status = ?, note = ? WHERE id = ?");
            $update->execute([$time, $finalStatus, $note, $existing['id']]);
        } else {
            // INSERT
            $insert = $conn->prepare("INSERT INTO attendances (user_id, date, time_in, status, note, created_by, location_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$staff_id, $date, $time, $finalStatus, $note, $kabid_id, 1]); // Set Location 1 as default for manual
        }
    } else {
        // TYPE: PULANG
        if ($existing) {
            $update = $conn->prepare("UPDATE attendances SET time_out = ?, status_out = ?, note = ? WHERE id = ?");
            $update->execute([$time, $finalStatus, $note, $existing['id']]);
        } else {
            // INSERT PULANG (langsung pasang status masuk 'Tanpa Data' atau biarkan krn hanya input pulang)
            $insert = $conn->prepare("INSERT INTO attendances (user_id, date, time_out, status_out, note, created_by, location_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Tanpa Data')");
            $insert->execute([$staff_id, $date, $time, $finalStatus, $note, $kabid_id, 1]);
        }
    }

    echo json_encode(["success" => true, "message" => "Presensi manual berhasil disimpan."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
