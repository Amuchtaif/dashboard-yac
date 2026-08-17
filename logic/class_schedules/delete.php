<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

$query_parts = [];
foreach ($_GET as $key => $value) {
    if ($key !== 'id' && $key !== 'success' && $key !== 'error') {
        $query_parts[$key] = $value;
    }
}
$query_string = http_build_query($query_parts);
$redirect_url = "../../views/class_schedules/index.php" . ($query_string ? '?' . $query_string . '&' : '?');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        // Check if journal entries exist for this schedule
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM class_journals WHERE class_schedule_id = ?");
        $check_stmt->execute([$id]);
        $has_journals = ($check_stmt->fetchColumn() > 0);

        if ($has_journals) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $stmt = $conn->prepare("UPDATE class_schedules SET is_active = 0, valid_until = ? WHERE id = ?");
            $stmt->execute([$yesterday, $id]);
            header("Location: " . $redirect_url . "success=" . urlencode("Jadwal berhasil di-arsipkan (memiliki riwayat jurnal/absensi)."));
        } else {
            $stmt = $conn->prepare("DELETE FROM class_schedules WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: " . $redirect_url . "success=" . urlencode("Jadwal berhasil dihapus"));
        }
    } catch (PDOException $e) {
        header("Location: " . $redirect_url . "error=" . urlencode("Kesalahan Database: " . $e->getMessage()));
    }
} else {
    header("Location: " . $redirect_url . "error=" . urlencode("ID tidak valid"));
}
?>