<?php
// portal_wali/logout.php
require_once __DIR__ . '/init.php';

// Hapus hanya variabel sesi wali murid (agar tidak mengganggu sesi admin jika berada dalam domain yang sama)
unset($_SESSION['guardian_id']);
unset($_SESSION['guardian_name']);
unset($_SESSION['guardian_phone']);
unset($_SESSION['guardian_relationship']);
unset($_SESSION['active_student_id']);

header("Location: " . portal_url('login.php?success=' . urlencode('Anda telah berhasil keluar dari akun.')));
exit;

