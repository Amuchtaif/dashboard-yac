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
$redirect_url = "../../views/lesson_periods/index.php" . ($query_string ? '?' . $query_string . '&' : '?');


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
    $db = new Database();
    $conn = $db->getConnection();

    $deleted_count = 0;
    $failed_count = 0;

    foreach ($ids as $id) {
        // Cek apakah jam ini digunakan di jadwal pelajaran
        $stmt_usage = $conn->prepare("SELECT id FROM class_schedules WHERE lesson_period_id = ? OR end_lesson_period_id = ? LIMIT 1");
        $stmt_usage->execute([$id, $id]);

        if ($stmt_usage->rowCount() > 0) {
            $failed_count++;
            continue;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM lesson_periods WHERE id = ?");
            $stmt->execute([$id]);
            $deleted_count++;
        } catch (PDOException $e) {
            $failed_count++;
        }
    }

    if ($deleted_count > 0) {
        Logger::activity(
            'Jam Pelajaran',
            'BULK_DELETE',
            "Menghapus massal $deleted_count jam pelajaran",
            [
                'table' => 'lesson_periods',
                'old_data' => ['deleted_count' => $deleted_count, 'failed_count' => $failed_count, 'deleted_ids' => $ids]
            ]
        );
    }

    if ($deleted_count > 0 && $failed_count == 0) {
        header("Location: " . $redirect_url . "success=" . urlencode("Berhasil menghapus " . $deleted_count . " jam pelajaran."));
    } else if ($deleted_count > 0 && $failed_count > 0) {
        header("Location: " . $redirect_url . "success=" . urlencode("Berhasil menghapus " . $deleted_count . " jam pelajaran. ") . "&error=" . urlencode($failed_count . " jam pelajaran gagal dihapus karena masih digunakan."));
    } else {
        header("Location: " . $redirect_url . "error=" . urlencode("Gagal menghapus jam pelajaran terpilih. Pastikan jam tidak sedang digunakan."));
    }
} else {
    header("Location: " . $redirect_url . "error=" . urlencode("Tidak ada jam pelajaran yang dipilih."));
}
?>
