<?php
// logic/guardians/delete.php
// Menghapus akun wali murid beserta relasi santri terhubung
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guardian_id = isset($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : 0;

    if ($guardian_id <= 0) {
        $_SESSION['error'] = "ID Wali Murid tidak valid";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Cek keberadaan akun wali murid
    $stmt = $conn->prepare("SELECT id, full_name, phone_number FROM guardians WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $guardian_id]);
    $guardian = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guardian) {
        $_SESSION['error'] = "Data wali murid tidak ditemukan";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. Hapus log OTP wali terkait
        $del_otp = $conn->prepare("DELETE FROM guardian_otps WHERE phone_number = :pn");
        $del_otp->execute([':pn' => $guardian['phone_number']]);

        // 2. Hapus keterhubungan dengan santri
        $del_rel = $conn->prepare("DELETE FROM guardian_students WHERE guardian_id = :gid");
        $del_rel->execute([':gid' => $guardian_id]);

        // 3. Hapus akun wali murid
        $del_g = $conn->prepare("DELETE FROM guardians WHERE id = :id");
        $del_g->execute([':id' => $guardian_id]);

        $conn->commit();

        $_SESSION['success'] = "Akun wali murid '{$guardian['full_name']}' berhasil dihapus secara permanen.";
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
    }

    header("Location: " . BASE_URL . "/views/guardians/index.php");
    exit;
}

header("Location: " . BASE_URL . "/views/guardians/index.php");
exit;

