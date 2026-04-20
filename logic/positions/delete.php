<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        // Optional: Check if used by employees
        $check = $conn->query("SELECT COUNT(*) FROM employees WHERE position_id = $id")->fetchColumn();
        if ($check > 0) {
            header("Location: " . BASE_URL . "/views/positions/index.php?error=" . urlencode("Tidak dapat menghapus: Jabatan masih digunakan oleh pegawai."));
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM positions WHERE id = :id");
        $stmt->execute([':id' => $id]);

        header("Location: " . BASE_URL . "/views/positions/index.php?success=" . urlencode("Jabatan berhasil dihapus"));
        exit;
    } catch (PDOException $e) {
        header("Location: " . BASE_URL . "/views/positions/index.php?error=" . urlencode("Kesalahan Database: " . $e->getMessage()));
        exit;
    }
}
?>