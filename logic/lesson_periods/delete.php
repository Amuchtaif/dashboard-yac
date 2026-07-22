<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

$id = isset($_GET['id']) ? $_GET['id'] : null;

$query_parts = [];
foreach ($_GET as $key => $value) {
    if ($key !== 'id' && $key !== 'success' && $key !== 'error') {
        $query_parts[$key] = $value;
    }
}
$query_string = http_build_query($query_parts);
$redirect_url = "../../views/lesson_periods/index.php" . ($query_string ? '?' . $query_string . '&' : '?');

if ($id) {
    $db = new Database();
    $conn = $db->getConnection();

    // Cek apakah jam ini digunakan di jadwal pelajaran
    $stmt_usage = $conn->prepare("SELECT id FROM class_schedules WHERE lesson_period_id = ? OR end_lesson_period_id = ? LIMIT 1");
    $stmt_usage->execute([$id, $id]);

    if ($stmt_usage->rowCount() > 0) {
        header("Location: " . $redirect_url . "error=" . urlencode("Tidak dapat menghapus jam karena masih digunakan dalam Jadwal Pelajaran."));
        exit;
    }

    try {
        $old_stmt = $conn->prepare("
            SELECT lp.period_number, lp.start_time, lp.end_time, eu.name as unit_name 
            FROM lesson_periods lp 
            LEFT JOIN education_units eu ON lp.education_unit_id = eu.id 
            WHERE lp.id = ? LIMIT 1
        ");
        $old_stmt->execute([$id]);
        $old_lp = $old_stmt->fetch(PDO::FETCH_ASSOC);

        $period_num = $old_lp['period_number'] ?? "ID $id";
        $unit_n = $old_lp['unit_name'] ?? "-";

        $stmt = $conn->prepare("DELETE FROM lesson_periods WHERE id = ?");
        $stmt->execute([$id]);

        Logger::activity(
            'Jam Pelajaran',
            'DELETE',
            "Menghapus Jam Ke-$period_num pada unit '$unit_n'",
            [
                'table' => 'lesson_periods',
                'record_id' => $id,
                'old_data' => $old_lp ?: null
            ]
        );

        header("Location: " . $redirect_url . "success=" . urlencode("Jam pelajaran berhasil dihapus."));
    } catch (PDOException $e) {
        header("Location: " . $redirect_url . "error=" . urlencode("Kesalahan Database: " . $e->getMessage()));
    }
} else {
    header("Location: " . $redirect_url . "error=" . urlencode("ID tidak valid."));
}
?>
