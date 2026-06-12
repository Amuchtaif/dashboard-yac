<?php
// scratch/test_tahfidz_api.php

echo "--- MEMULAI PENGUJIAN API ABSENSI TAHFIDZ ---\n\n";

// Load koneksi database
require_once __DIR__ . '/../config/db_mysqli.php';

if (!isset($mysqli)) {
    die("Koneksi database gagal\n");
}

// Set variabel server dasar untuk CLI
$_SERVER['REQUEST_METHOD'] = 'GET';

// 1. Cari group_id yang valid dari database
$group_res = $mysqli->query("SELECT id, group_name FROM halaqah_groups LIMIT 1");
if ($group_res->num_rows === 0) {
    die("Error: Tidak ada kelompok halaqah di database. Jalankan setup data terlebih dahulu.\n");
}
$group = $group_res->fetch_assoc();
$group_id = $group['id'];
$group_name = $group['group_name'];
echo "Ditemukan kelompok halaqah: ID = $group_id, Nama = '$group_name'\n";

// Cari anggota kelompok tersebut
$members_res = $mysqli->query("SELECT student_id FROM halaqah_members WHERE group_id = $group_id");
if ($members_res->num_rows === 0) {
    die("Error: Kelompok halaqah '$group_name' tidak memiliki anggota santri.\n");
}
$students = [];
while ($row = $members_res->fetch_assoc()) {
    $students[] = $row['student_id'];
}
echo "Jumlah anggota halaqah: " . count($students) . " santri.\n";

// Set parameter pengujian
$date = '2026-06-09';
$session = 'Pagi';
$teacher_id = 1; // ID Pengampu dummy

// Bersihkan data absensi untuk hari ini dari database agar mulai dari kondisi bersih
$mysqli->query("DELETE FROM tahfidz_attendance WHERE date = '$date' AND session = '$session'");
echo "Membersihkan data absensi dummy tanggal $date dan sesi $session... Selesai.\n\n";

// ==========================================
// PENGUJIAN 1: Mengambil absensi (GET) sebelum dikirim
// ==========================================
echo "=== PENGUJIAN 1: GET ABSENSI SEBELUM SUBMIT ===\n";
$_GET['group_id'] = $group_id;
$_GET['date'] = $date;
$_GET['session'] = $session;

ob_start();
include __DIR__ . '/../api/tahfidz/get_student_attendance.php';
$get_output = ob_get_clean();

$get_data = json_decode($get_output, true);
if (!$get_data || !isset($get_data['success']) || !$get_data['success']) {
    die("Gagal: GET absensi mengembalikan respon error: $get_output\n");
}

echo "Berhasil! Jumlah data yang dikembalikan: " . $get_data['count'] . "\n";
echo "Verifikasi status semua santri harus NULL (belum diabsen):\n";
$all_null = true;
foreach ($get_data['data'] as $record) {
    echo "- Santri ID " . $record['student_id'] . " ('" . $record['student_name'] . "'): status = " . ($record['status'] ?? 'NULL') . "\n";
    if ($record['status'] !== null) {
        $all_null = false;
    }
}

if ($all_null) {
    echo "PENGUJIAN 1 BERHASIL! (Semua santri dikembalikan dengan status NULL/belum absen)\n\n";
} else {
    echo "PENGUJIAN 1 GAGAL! Ada santri yang memiliki status padahal data harusnya kosong.\n\n";
}

// Reset GET parameters
$_GET = [];

// ==========================================
// PENGUJIAN 2: Mengirim absensi (POST) pertama kali
// ==========================================
echo "=== PENGUJIAN 2: POST ABSENSI PERTAMA KALI ===\n";

// Mock input JSON untuk php://input
$payload = [
    "date" => $date,
    "session" => $session,
    "teacher_id" => $teacher_id,
    "students" => []
];

// Set semua santri sebagai 'Hadir' kecuali satu sebagai 'Sakit'
foreach ($students as $index => $s_id) {
    $status = ($index === 0) ? 'Sakit' : 'Hadir';
    $payload['students'][] = [
        "student_id" => $s_id,
        "status" => $status
    ];
}

$payload_json = json_encode($payload);
file_put_contents(__DIR__ . '/test_payload.json', $payload_json);
echo "Payload simulasi berhasil ditulis ke 'test_payload.json'.\n";

// Jalankan submit_student_attendance.php dengan mengarahkan input dari test_payload.json
$cmd = "php " . escapeshellarg(__DIR__ . '/../api/tahfidz/submit_student_attendance.php') . " < " . escapeshellarg(__DIR__ . '/test_payload.json');
echo "Menjalankan perintah: $cmd\n";
$submit_output = shell_exec($cmd);
echo "Output API:\n$submit_output\n";

$submit_data = json_decode($submit_output, true);
if ($submit_data && $submit_data['success']) {
    echo "PENGUJIAN 2 BERHASIL! Absensi berhasil diproses.\n\n";
} else {
    die("PENGUJIAN 2 GAGAL! Gagal memproses absensi.\n\n");
}

// ==========================================
// PENGUJIAN 3: GET ABSENSI SETELAH SUBMIT
// ==========================================
echo "=== PENGUJIAN 3: GET ABSENSI SETELAH SUBMIT ===\n";
$_GET['group_id'] = $group_id;
$_GET['date'] = $date;
$_GET['session'] = $session;

ob_start();
include __DIR__ . '/../api/tahfidz/get_student_attendance.php';
$get_output2 = ob_get_clean();

$get_data2 = json_decode($get_output2, true);
echo "Berhasil! Data setelah absen dikirim:\n";
foreach ($get_data2['data'] as $record) {
    echo "- Santri ID " . $record['student_id'] . " ('" . $record['student_name'] . "'): status = " . ($record['status'] ?? 'NULL') . "\n";
}
echo "PENGUJIAN 3 BERHASIL!\n\n";

$_GET = [];

// ==========================================
// PENGUJIAN 4: Mengirim absensi (POST) kedua kali (UPDATE status)
// ==========================================
echo "=== PENGUJIAN 4: POST UPDATE STATUS (Mencegah Duplikasi) ===\n";

// Ubah santri index 0 yang tadinya 'Sakit' menjadi 'Izin'
// Dan santri lainnya tetap 'Hadir'
$payload2 = $payload;
$payload2['students'][0]['status'] = 'Izin';

$payload_json2 = json_encode($payload2);
file_put_contents(__DIR__ . '/test_payload.json', $payload_json2);

$submit_output2 = shell_exec($cmd);
echo "Output API:\n$submit_output2\n";

// Cek jumlah baris di database untuk hari ini dan sesi ini
$count_res = $mysqli->query("SELECT COUNT(*) as total FROM tahfidz_attendance WHERE date = '$date' AND session = '$session'");
$count_row = $count_res->fetch_assoc();
$total_rows = intval($count_row['total']);

echo "Total baris absensi di database untuk hari ini: $total_rows baris.\n";
echo "Jumlah anggota halaqah asli: " . count($students) . "\n";

if ($total_rows === count($students)) {
    echo "PENGUJIAN 4 BERHASIL! Tidak terjadi duplikasi baris database (logika update berfungsi).\n\n";
} else {
    echo "PENGUJIAN 4 GAGAL! Terjadi duplikasi data absensi di database (total baris: $total_rows, harusnya: " . count($students) . ").\n\n";
}

// ==========================================
// PENGUJIAN 5: GET ABSENSI SETELAH UPDATE
// ==========================================
echo "=== PENGUJIAN 5: GET ABSENSI SETELAH UPDATE ===\n";
$_GET['group_id'] = $group_id;
$_GET['date'] = $date;
$_GET['session'] = $session;

ob_start();
include __DIR__ . '/../api/tahfidz/get_student_attendance.php';
$get_output3 = ob_get_clean();

$get_data3 = json_decode($get_output3, true);
echo "Verifikasi status santri ke-1 harus berubah menjadi 'Izin':\n";
foreach ($get_data3['data'] as $record) {
    if ($record['student_id'] == $students[0]) {
        echo "- Status Santri ID " . $students[0] . " ('" . $record['student_name'] . "'): " . $record['status'] . " (Diharapkan: 'Izin')\n";
        if ($record['status'] === 'Izin') {
            echo "PENGUJIAN 5 BERHASIL!\n\n";
        } else {
            echo "PENGUJIAN 5 GAGAL! Status tidak berubah.\n\n";
        }
        break;
    }
}

$_GET = [];

// Bersihkan data sampah pengujian
$mysqli->query("DELETE FROM tahfidz_attendance WHERE date = '$date' AND session = '$session'");
@unlink(__DIR__ . '/test_payload.json');
echo "Data uji coba berhasil dibersihkan kembali.\n";
echo "--- SEMUA PENGUJIAN SELESAI DENGAN SUKSES! ---\n";
?>
