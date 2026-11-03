<?php
// logic/guardians/update.php
// Memperbarui data profil wali murid (Nama, Nomor WhatsApp, Hubungan, Status)
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guardian_id = isset($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : 0;
    $full_name = trim($_POST['full_name'] ?? '');
    $raw_phone = trim($_POST['phone_number'] ?? '');
    $relationship = trim($_POST['relationship'] ?? 'Ayah');
    $status = trim($_POST['status'] ?? 'active');

    if ($guardian_id <= 0) {
        $_SESSION['error'] = "ID Wali Murid tidak valid";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    if (empty($full_name)) {
        $_SESSION['error'] = "Nama lengkap wali murid wajib diisi";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);
    if (empty($clean_phone) || !ctype_digit($raw_phone)) {
        $_SESSION['error'] = "Nomor WhatsApp hanya boleh berisi angka (tanpa huruf, spasi, atau tanda khusus)";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    if (strlen($clean_phone) < 9) {
        $_SESSION['error'] = "Nomor WhatsApp minimal 9 digit angka";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    // Normalisasi awalan nomor: jika 62 ganti jadi 08
    if (strpos($clean_phone, '62') === 0) {
        $clean_phone = '0' . substr($clean_phone, 2);
    }

    if (!in_array($relationship, ['Ayah', 'Ibu', 'Wali'])) {
        $relationship = 'Ayah';
    }

    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Pastikan data wali murid ada
    $stmt = $conn->prepare("SELECT id, full_name, phone_number FROM guardians WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $guardian_id]);
    $guardian = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guardian) {
        $_SESSION['error'] = "Data wali murid tidak ditemukan";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    // Cek duplikasi nomor WA dengan akun wali lain
    $alt_phone = (strpos($clean_phone, '0') === 0) ? '62' . substr($clean_phone, 1) : '0' . substr($clean_phone, 2);
    $chk = $conn->prepare("
        SELECT id, full_name FROM guardians 
        WHERE (phone_number = :p1 OR phone_number = :p2) 
          AND id != :id 
        LIMIT 1
    ");
    $chk->execute([
        ':p1' => $clean_phone,
        ':p2' => $alt_phone,
        ':id' => $guardian_id
    ]);
    $duplicate = $chk->fetch(PDO::FETCH_ASSOC);

    if ($duplicate) {
        $_SESSION['error'] = "Nomor WhatsApp '{$clean_phone}' sudah digunakan oleh wali murid lain ({$duplicate['full_name']}).";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    // Update data di database
    $upd = $conn->prepare("
        UPDATE guardians 
        SET full_name = :fn,
            phone_number = :pn,
            relationship = :rel,
            status = :st,
            updated_at = NOW()
        WHERE id = :id
    ");
    $upd->execute([
        ':fn' => $full_name,
        ':pn' => $clean_phone,
        ':rel' => $relationship,
        ':st' => $status,
        ':id' => $guardian_id
    ]);

    // Update juga relasi kontak di guardian_students jika ada
    $upd_rel = $conn->prepare("UPDATE guardian_students SET relationship = :rel WHERE guardian_id = :gid");
    $upd_rel->execute([':rel' => $relationship, ':gid' => $guardian_id]);

    $_SESSION['success'] = "Data wali murid '{$full_name}' berhasil diperbarui.";
    header("Location: " . BASE_URL . "/views/guardians/index.php");
    exit;
}

header("Location: " . BASE_URL . "/views/guardians/index.php");
exit;

