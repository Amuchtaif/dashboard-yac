<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("DELETE FROM locations WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
        header("Location: ../../views/settings/locations.php?success=Lokasi+berhasil+dihapus");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: ../../views/settings/locations.php?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
        header("Location: ../../views/settings/locations.php?error=ID+tidak+valid");
    exit;
}
