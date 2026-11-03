<?php
// logic/guardians/update_password.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guardian_id = isset($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : 0;
    $new_password = trim($_POST['new_password'] ?? '');

    if ($guardian_id <= 0) {
        $_SESSION['error'] = "ID Wali Murid tidak valid";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    if (empty($new_password) || strlen($new_password) < 6) {
        $_SESSION['error'] = "Kata sandi baru minimal 6 karakter";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, full_name, phone_number FROM guardians WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $guardian_id]);
    $guardian = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guardian) {
        $_SESSION['error'] = "Data wali murid tidak ditemukan";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    $hash = password_hash($new_password, PASSWORD_BCRYPT);
    $update = $conn->prepare("UPDATE guardians SET password_hash = :hash WHERE id = :id");
    $update->execute([
        ':hash' => $hash,
        ':id' => $guardian_id
    ]);

    $clean_phone = preg_replace('/[^0-9]/', '', $guardian['phone_number']);
    if (strpos($clean_phone, '0') === 0) {
        $clean_phone = '62' . substr($clean_phone, 1);
    }

    $success_msg = "Kata sandi untuk {$guardian['full_name']} berhasil diperbarui.";
    $_SESSION['success'] = $success_msg;
    $params = [
        'pwd_reset_id' => $guardian_id,
        'pwd_reset_name' => $guardian['full_name'],
        'pwd_reset_phone' => $clean_phone,
        'pwd_reset_plain' => $new_password
    ];

    header("Location: " . BASE_URL . "/views/guardians/index.php?" . http_build_query($params));
    exit;
}

header("Location: " . BASE_URL . "/views/guardians/index.php");
exit;
