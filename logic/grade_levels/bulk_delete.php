<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

$query_parts = [];
foreach ($_GET as $key => $value) {
    if ($key !== 'success' && $key !== 'error') {
        $query_parts[$key] = $value;
    }
}
$query_string = http_build_query($query_parts);
$redirect_url = "../../views/grade_levels/index.php" . ($query_string ? '?' . $query_string . '&' : '?');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
    $db = new Database();
    $conn = $db->getConnection();

    $deleted_count = 0;
    $failed_count = 0;

    foreach ($ids as $id) {
        try {
            $stmt = $conn->prepare("DELETE FROM grade_levels WHERE id = ?");
            $stmt->execute([$id]);
            $deleted_count++;
        } catch (PDOException $e) {
            $failed_count++;
        }
    }

    if ($deleted_count > 0 && $failed_count == 0) {
        header("Location: " . $redirect_url . "success=" . urlencode("Berhasil menghapus " . $deleted_count . " kelas."));
    } else if ($deleted_count > 0 && $failed_count > 0) {
        header("Location: " . $redirect_url . "success=" . urlencode("Berhasil menghapus " . $deleted_count . " kelas. ") . "&error=" . urlencode($failed_count . " kelas gagal dihapus karena masih ada data siswa atau jadwal di dalamnya."));
    } else {
        header("Location: " . $redirect_url . "error=" . urlencode("Gagal menghapus kelas terpilih. Pastikan kelas tidak sedang memiliki riwayat siswa atau jadwal."));
    }
} else {
    header("Location: " . $redirect_url . "error=" . urlencode("Tidak ada kelas yang dipilih."));
}
?>
