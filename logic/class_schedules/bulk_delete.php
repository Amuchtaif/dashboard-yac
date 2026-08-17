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
$redirect_url = "../../views/class_schedules/index.php" . ($query_string ? '?' . $query_string . '&' : '?');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $to_delete = [];
        $to_archive = [];

        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM class_journals WHERE class_schedule_id = ?");
        foreach ($ids as $sch_id) {
            $check_stmt->execute([$sch_id]);
            if ($check_stmt->fetchColumn() > 0) {
                $to_archive[] = $sch_id;
            } else {
                $to_delete[] = $sch_id;
            }
        }

        if (!empty($to_archive)) {
            $inArchive = implode(',', array_fill(0, count($to_archive), '?'));
            $arch_stmt = $conn->prepare("UPDATE class_schedules SET is_active = 0, valid_until = ? WHERE id IN ($inArchive)");
            $arch_stmt->execute(array_merge([$yesterday], $to_archive));
        }

        if (!empty($to_delete)) {
            $inDel = implode(',', array_fill(0, count($to_delete), '?'));
            $del_stmt = $conn->prepare("DELETE FROM class_schedules WHERE id IN ($inDel)");
            $del_stmt->execute($to_delete);
        }

        $msg = "Berhasil memproses " . count($ids) . " jadwal";
        if (count($to_archive) > 0) {
            $msg .= " (" . count($to_archive) . " di-arsipkan karena memiliki riwayat jurnal)";
        }

        header("Location: " . $redirect_url . "success=" . urlencode($msg));
    } catch (PDOException $e) {
        header("Location: " . $redirect_url . "error=" . urlencode("Kesalahan Database: " . $e->getMessage()));
    }
} else {
    header("Location: " . $redirect_url . "error=" . urlencode("Tidak ada jadwal yang dipilih"));
}
?>
