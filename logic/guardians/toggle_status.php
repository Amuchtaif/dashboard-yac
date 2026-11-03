<?php
// logic/guardians/toggle_status.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guardian_id = isset($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : 0;
    $target_status = isset($_POST['status']) && in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'active';

    if ($guardian_id <= 0) {
        $_SESSION['error'] = "ID Wali Murid tidak valid";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, full_name, status FROM guardians WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $guardian_id]);
    $guardian = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guardian) {
        $_SESSION['error'] = "Data wali murid tidak ditemukan";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    $update = $conn->prepare("UPDATE guardians SET status = :status WHERE id = :id");
    $update->execute([
        ':status' => $target_status,
        ':id' => $guardian_id
    ]);

    $msg = ($target_status === 'inactive') 
        ? "Akses portal untuk {$guardian['full_name']} telah berhasil diputus (dinonaktifkan)."
        : "Akses portal untuk {$guardian['full_name']} telah berhasil diaktifkan kembali.";

    $_SESSION['success'] = $msg;
    header("Location: " . BASE_URL . "/views/guardians/index.php");
    exit;
}

header("Location: " . BASE_URL . "/views/guardians/index.php");
exit;
