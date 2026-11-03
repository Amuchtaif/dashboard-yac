<?php
// logic/guardians/link_student.php
// Menghubungkan santri ke wali murid secara manual oleh admin
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = trim($_POST['mode'] ?? 'existing'); // 'existing' atau 'new_guardian'
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $relationship = trim($_POST['relationship'] ?? 'Ayah');
    if (!in_array($relationship, ['Ayah', 'Ibu', 'Wali'])) {
        $relationship = 'Ayah';
    }

    $db = new Database();
    $conn = $db->getConnection();

    if ($student_id <= 0) {
        $_SESSION['error'] = "Santri wajib dipilih";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    // Cek keberadaan santri (Hanya santri dengan status AKTIF)
    $chk_student = $conn->prepare("SELECT id, nama_siswa, status FROM students WHERE id = :sid AND status = 'Aktif' LIMIT 1");
    $chk_student->execute([':sid' => $student_id]);
    $student = $chk_student->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['error'] = "Santri tidak ditemukan atau status santri tidak aktif";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    $guardian_id = 0;
    $guardian_name = '';

    if ($mode === 'existing') {
        $guardian_id = isset($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : 0;
        if ($guardian_id <= 0) {
            $_SESSION['error'] = "Wali murid wajib dipilih";
            header("Location: " . BASE_URL . "/views/guardians/index.php");
            exit;
        }

        $chk_guard = $conn->prepare("SELECT id, full_name FROM guardians WHERE id = :gid LIMIT 1");
        $chk_guard->execute([':gid' => $guardian_id]);
        $g = $chk_guard->fetch(PDO::FETCH_ASSOC);
        if (!$g) {
            $_SESSION['error'] = "Akun wali murid tidak ditemukan";
            header("Location: " . BASE_URL . "/views/guardians/index.php");
            exit;
        }
        $guardian_name = $g['full_name'];
    } else {
        // Mode new_guardian: Buat akun wali murid baru langsung dari admin
        $full_name = trim($_POST['guardian_name'] ?? '');
        $phone = trim($_POST['guardian_phone'] ?? '');
        $password = trim($_POST['guardian_password'] ?? '');

        $raw_phone = trim($_POST['guardian_phone'] ?? '');
        $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);

        if (empty($full_name) || empty($clean_phone)) {
            $_SESSION['error'] = "Nama wali dan nomor WhatsApp wajib diisi";
            header("Location: " . BASE_URL . "/views/guardians/index.php");
            exit;
        }

        if (!ctype_digit($raw_phone)) {
            $_SESSION['error'] = "Nomor WhatsApp hanya boleh berisi angka";
            header("Location: " . BASE_URL . "/views/guardians/index.php");
            exit;
        }

        if (strlen($clean_phone) < 9) {
            $_SESSION['error'] = "Nomor WhatsApp minimal 9 digit angka";
            header("Location: " . BASE_URL . "/views/guardians/index.php");
            exit;
        }

        if (strpos($clean_phone, '62') === 0) {
            $clean_phone = '0' . substr($clean_phone, 2);
        }

        if (empty($password)) {
            $password = '123456'; // Default password jika tidak diisi
        }

        // Cek apakah nomor WA sudah ada
        $chk_exist = $conn->prepare("SELECT id, full_name FROM guardians WHERE phone_number = :p LIMIT 1");
        $chk_exist->execute([':p' => $clean_phone]);
        $exist = $chk_exist->fetch(PDO::FETCH_ASSOC);

        if ($exist) {
            $guardian_id = (int)$exist['id'];
            $guardian_name = $exist['full_name'];
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins_g = $conn->prepare("
                INSERT INTO guardians (phone_number, full_name, password_hash, relationship, status, created_at)
                VALUES (:p, :fn, :pwd, :rel, 'active', NOW())
            ");
            $ins_g->execute([
                ':p' => $clean_phone,
                ':fn' => $full_name,
                ':pwd' => $hash,
                ':rel' => $relationship
            ]);
            $guardian_id = (int)$conn->lastInsertId();
            $guardian_name = $full_name;
        }
    }

    // Hubungkan di tabel guardian_students
    $ins_link = $conn->prepare("
        INSERT INTO guardian_students (guardian_id, student_id, relationship, is_primary, is_verified, created_at)
        VALUES (:gid, :sid, :rel, 1, 1, NOW())
        ON DUPLICATE KEY UPDATE relationship = :rel_upd, is_verified = 1
    ");
    $ins_link->execute([
        ':gid' => $guardian_id,
        ':sid' => $student_id,
        ':rel' => $relationship,
        ':rel_upd' => $relationship
    ]);

    $msg = "Santri '{$student['nama_siswa']}' berhasil dihubungkan dengan wali '{$guardian_name}'.";
    $_SESSION['success'] = $msg;
    header("Location: " . BASE_URL . "/views/guardians/index.php");
    exit;
}

header("Location: " . BASE_URL . "/views/guardians/index.php");
exit;

