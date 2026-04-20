<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $level = (int) $_POST['level'];

    if (empty($name) || empty($level)) {
        header("Location: " . BASE_URL . "/views/positions/form.php?error=" . urlencode("Semua kolom wajib diisi"));
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("INSERT INTO positions (name, level) VALUES (:name, :level)");
        $stmt->execute([':name' => $name, ':level' => $level]);

        header("Location: " . BASE_URL . "/views/positions/index.php?success=" . urlencode("Jabatan berhasil ditambahkan"));
        exit;
    } catch (PDOException $e) {
        header("Location: " . BASE_URL . "/views/positions/forms.php?error=" . urlencode("Kesalahan Database: " . $e->getMessage()));
        exit;
    }
}
?>